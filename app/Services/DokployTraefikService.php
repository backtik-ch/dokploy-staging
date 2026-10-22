<?php

namespace App\Services;

use App\Exceptions\MaintenanceException;
use App\Models\Project;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DokployTraefikService
{
    public function lockKey(Project $project, string $path): string
    {
        return 'maintenance:'.hash('sha256', $this->apiUrl($project->dokploy?->base_url ?? '').'|'.$project->server_id.'|'.$path);
    }

    public function read(Project $project, string $path): string
    {
        try {
            $response = $this->request($project)->get('settings.readTraefikFile', $this->parameters($project, $path));
        } catch (ConnectionException) {
            throw new MaintenanceException('Connexion à Dokploy impossible pendant la lecture du fichier Traefik.');
        }

        $this->ensureSuccess($response);
        $content = $response->json();

        if (! is_string($content)) {
            throw new MaintenanceException('La réponse Dokploy ne contient pas le texte du fichier Traefik.');
        }

        return $content;
    }

    public function write(Project $project, string $path, string $content): void
    {
        try {
            $response = $this->request($project)->post('settings.updateTraefikFile', [
                ...$this->parameters($project, $path),
                'traefikConfig' => $content,
            ]);
        } catch (ConnectionException) {
            throw new MaintenanceException('Connexion interrompue pendant l’écriture. L’état distant doit être vérifié.');
        }

        $this->ensureSuccess($response);
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
