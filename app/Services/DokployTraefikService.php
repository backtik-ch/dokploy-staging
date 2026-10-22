<?php

namespace App\Services;

use App\Exceptions\MaintenanceException;
use App\Models\Project;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DokployTraefikService
{
    public function lockKey(Project $project, string $path): string
    {
        return 'maintenance:'.hash('sha256', $this->apiUrl($project->dokploy?->base_url ?? '').'|'.$project->server_id.'|'.$path);
    }

    public function read(Project $project, string $path, array $context = []): string
    {
        $context += $this->requestContext($project, $path, 'settings.readTraefikFile', 'GET');
        $startedAt = hrtime(true);
        Log::debug('maintenance.http.request', $context);

        try {
            $response = $this->request($project)->get('settings.readTraefikFile', $this->parameters($project, $path));
        } catch (ConnectionException) {
            Log::error('maintenance.http.connection_failed', $context + $this->timing($startedAt));

            throw new MaintenanceException('Connexion à Dokploy impossible pendant la lecture du fichier Traefik.');
        }

        $this->logResponse($response, $context, $startedAt);
        $this->ensureSuccess($response);
        $content = $response->json();

        if (! is_string($content)) {
            Log::warning('maintenance.http.invalid_response', $context + ['response_type' => get_debug_type($content)]);

            throw new MaintenanceException('La réponse Dokploy ne contient pas le texte du fichier Traefik.');
        }

        return $content;
    }

    public function write(Project $project, string $path, string $content, array $context = []): void
    {
        $context += $this->requestContext($project, $path, 'settings.updateTraefikFile', 'POST');
        $startedAt = hrtime(true);
        Log::debug('maintenance.http.request', $context);

        try {
            $response = $this->request($project)->post('settings.updateTraefikFile', [
                ...$this->parameters($project, $path),
                'traefikConfig' => $content,
            ]);
        } catch (ConnectionException) {
            Log::error('maintenance.http.connection_failed', $context + $this->timing($startedAt));

            throw new MaintenanceException('Connexion interrompue pendant l’écriture. L’état distant doit être vérifié.');
        }

        $this->logResponse($response, $context, $startedAt);
        $this->ensureSuccess($response);
    }

    private function requestContext(Project $project, string $path, string $endpoint, string $method): array
    {
        return [
            'project_id' => $project->getKey(),
            'dokploy_id' => $project->dokploy_id,
            'server_id' => $project->server_id ?: 'local',
            'path' => $path,
            'endpoint' => $endpoint,
            'method' => $method,
        ];
    }

    private function logResponse(Response $response, array $context, int $startedAt): void
    {
        $headers = [];

        foreach (['Content-Type', 'Cache-Control', 'Age', 'X-Cache', 'CF-Cache-Status', 'ETag', 'Last-Modified'] as $name) {
            if (filled($value = $response->header($name))) {
                $headers[$name] = $value;
            }
        }

        $data = $response->json();
        $metadata = [
            'http_status' => $response->status(),
            'response_bytes' => strlen($response->body()),
            'response_type' => get_debug_type($data),
            'response_headers' => $headers,
        ];

        if ($context['method'] === 'POST') {
            $metadata['boolean_result'] = is_bool($data) ? $data : null;
            $metadata['success_result'] = is_array($data) && is_bool($data['success'] ?? null) ? $data['success'] : null;
        }

        Log::log($response->successful() ? 'info' : 'warning', 'maintenance.http.response', $context + $this->timing($startedAt) + $metadata);
    }

    private function timing(int $startedAt): array
    {
        return ['duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2)];
    }

    private function request(Project $project): PendingRequest
    {
        $dokploy = $project->dokploy;

        if (! $dokploy || blank($dokploy->base_url) || blank($dokploy->token)) {
            throw new MaintenanceException('Configurez une connexion Dokploy et son token dans les réglages du projet.');
        }

        return Http::baseUrl($this->apiUrl($dokploy->base_url))
            ->withHeaders(['x-api-key' => $dokploy->token, 'Cache-Control' => 'no-cache'])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function apiUrl(string $url): string
    {
        return preg_replace('~/api$~', '', rtrim($url, '/')).'/api/';
    }

    private function parameters(Project $project, string $path): array
    {
        return array_filter([
            'path' => $path,
            'serverId' => $project->server_id,
        ], fn ($value): bool => filled($value));
    }

    private function ensureSuccess(Response $response): void
    {
        if (! $response->successful()) {
            // Do not expose a response body which may contain credentials or configuration.
            throw new MaintenanceException('Dokploy a refusé l’opération (HTTP '.$response->status().'). Vérifiez le fichier, le serveur et les permissions du token.');
        }
    }
}
