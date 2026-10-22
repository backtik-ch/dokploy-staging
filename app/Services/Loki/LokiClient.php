<?php

namespace App\Services\Loki;

use App\Exceptions\LokiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LokiClient
{
    public function configured(): bool
    {
        return filled(config('loki.url'));
    }

    public function get(string $path, array $parameters): array
    {
        if (! $this->configured()) {
            throw new LokiException('Renseignez LOKI_URL dans l’environnement de l’application pour connecter les logs.');
        }

        $request = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(config('loki.timeout'));

        if (filled(config('loki.token'))) {
            $request->withToken(config('loki.token'));
        } elseif (filled(config('loki.username'))) {
            $request->withBasicAuth(config('loki.username'), (string) config('loki.password'));
        }

        if (filled(config('loki.tenant_id'))) {
            $request->withHeaders(['X-Scope-OrgID' => config('loki.tenant_id')]);
        }

        try {
            $response = $request->get(rtrim(config('loki.url'), '/').'/loki/api/v1/'.$path, $parameters);
        } catch (ConnectionException) {
            throw new LokiException('Loki est injoignable. Vérifiez son adresse et la connexion réseau, puis réessayez.');
        }

        if (! $response->successful()) {
            throw new LokiException(match ($response->status()) {
                401, 403 => 'Accès à Loki refusé. Vérifiez les identifiants et le tenant configurés.',
                400 => 'Loki a refusé la requête. Vérifiez le sélecteur configuré et réduisez la période ou les filtres.',
                429 => 'Loki reçoit trop de requêtes. Patientez quelques instants avant de réessayer.',
                default => 'Loki est temporairement indisponible (HTTP '.$response->status().'). Réessayez dans un instant.',
            });
        }

        $body = $response->json();

        if (is_array($body) && ($body['status'] ?? null) === 'success') {
            $data = $body['data'] ?? null;

            if (is_array($data)) {
                return $data;
            }

            // Loki can omit data or return null when no label names or values exist.
            if ($data === null && ($path === 'labels' || preg_match('#^label/[a-zA-Z_][a-zA-Z0-9_]*/values$#D', $path))) {
                return [];
            }
        }

        throw new LokiException('Loki a renvoyé une réponse inattendue. Vérifiez la configuration du service.');
    }

    public function cached(string $path, array $parameters, int $seconds = 60): array
    {
        // Isolate caches by connection, credentials and tenant, including after credential rotation.
        $key = 'loki:'.hash('sha256', serialize([config('loki'), $path, $parameters]));

        return Cache::remember($key, $seconds, fn (): array => $this->get($path, $parameters));
    }
}
