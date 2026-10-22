<?php

namespace App\Services;

use App\Exceptions\StagingDeletionException;
use App\Models\Project;
use App\Models\Staging;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class StagingDeletionService
{
    public function delete(Staging $staging): bool
    {
        if ($staging->trashed()) {
            return true;
        }

        $staging->loadMissing('project.dokploy');
        $project = $staging->project;

        if (! $project?->dokploy || blank($project->dokploy->base_url) || blank($project->dokploy->token)) {
            throw new StagingDeletionException('Connexion Dokploy indisponible. Le staging local est conservé.');
        }

        if (filled($staging->compose_id)) {
            $this->deleteResource($project, 'compose.delete', [
                'composeId' => $staging->compose_id,
                'deleteVolumes' => true,
            ], 'compose');
        }

        if (filled($staging->environment_id)) {
            $this->deleteResource($project, 'environment.remove', [
                'environmentId' => $staging->environment_id,
            ], 'environment');
        }

        return (bool) $staging->delete();
    }

    private function deleteResource(Project $project, string $endpoint, array $payload, string $resource): void
    {
        try {
            $response = Http::withHeaders(['x-api-key' => $project->dokploy->token])
                ->baseUrl(rtrim($project->dokploy->base_url, '/'))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post('/api/trpc/'.$endpoint.'?batch=1', (object) ['0' => ['json' => $payload]]);
        } catch (ConnectionException) {
            throw new StagingDeletionException('Connexion à Dokploy impossible. Le staging local est conservé pour réessayer.');
        }

        $data = $response->json();
        $error = data_get($data, '0.error.json') ?? data_get($data, '0.error')
            ?? data_get($data, 'error.json') ?? data_get($data, 'error');

        if (is_array($error) && $this->isMissingResource($response, $error, $resource)) {
            return;
        }

        if (! $response->successful() || $error !== null) {
            throw new StagingDeletionException('Suppression refusée par Dokploy ('.$endpoint.', HTTP '.$response->status().'). Le staging local est conservé. Vérifiez les permissions et la disponibilité de Dokploy.');
        }

        // A proxy error page or a malformed 2xx response must not count as a confirmed deletion.
        if (! is_array($data) || ! is_array($data[0] ?? null) || ! array_key_exists('result', $data[0])) {
            throw new StagingDeletionException('Réponse Dokploy invalide pendant la suppression. Le staging local est conservé.');
        }
    }

    private function isMissingResource(Response $response, array $error, string $resource): bool
    {
        $code = data_get($error, 'data.code');

        if (in_array($response->status(), [401, 403, 429], true) || in_array($code, ['UNAUTHORIZED', 'FORBIDDEN', 'TOO_MANY_REQUESTS'], true)) {
            return false;
        }

        // Dokploy uses "Compose not found" / "Environment not found". Some older
        // versions wrap these messages in BAD_REQUEST or INTERNAL_SERVER_ERROR.
        // Do not mistake a missing API procedure, server or permission for a missing staging.
        $name = $resource === 'compose' ? '(?:compose|service)' : 'environment';

        return preg_match('/\b'.$name.'\b(?:\s+with\s+id\s+\S+)?\s+(?:not found|does not exist)\b/i', $error['message'] ?? '') === 1;
    }
}
