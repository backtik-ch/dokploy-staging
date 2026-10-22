<?php

namespace App\Services;

use App\Exceptions\MaintenanceException;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MaintenanceService
{
    private const FLAG = '/\{\{\s*\$maintenance_enabled\s*:=\s*([01])\s*\}\}/';

    public function __construct(private readonly DokployTraefikService $traefik) {}

    public function refresh(Project $project): bool
    {
        return $this->operate($project, null);
    }

    public function refreshCached(Project $project): ?bool
    {
        $project->refresh()->load('dokploy');
        $path = $this->resolvePath($project->maintenance_yml_file);
        $cached = Cache::get($this->statusCacheKey($project, $path));

        if ($cached === null) {
            return $this->refresh($project);
        }

        Log::debug('maintenance.cache.hit', [
            'project_id' => $project->getKey(),
            'path' => $path,
            'server_id' => $project->server_id ?: 'local',
            'maintenance_enabled' => $cached['maintenance_enabled'],
            'has_error' => $cached['maintenance_error'] !== null,
        ]);

        $project->update($cached);

        // A cached failure remains visible on the project without raising a new request error.
        return $cached['maintenance_enabled'];
    }

    public function setEnabled(Project $project, bool $enabled): bool
    {
        return $this->operate($project, $enabled);
    }

    public function resolvePath(?string $file): string
    {
        $file = trim($file ?? '');

        if (! preg_match('/\A[^\x00-\x1F\x7F]+\.ya?ml\z/', $file) || in_array('..', explode('/', $file), true)) {
            throw new MaintenanceException('Indiquez un fichier .yml ou .yaml valide, sans traversée de répertoire.');
        }

        return str_starts_with($file, '/') ? $file : '/etc/dokploy/traefik/dynamic/'.$file;
    }

    private function operate(Project $project, ?bool $enabled): bool
    {
        $project->refresh();
        $project->load('dokploy');
        $startedAt = hrtime(true);
        $context = [
            'operation_id' => (string) Str::uuid(),
            'project_id' => $project->getKey(),
            'dokploy_id' => $project->dokploy_id,
            'dokploy_host' => parse_url($project->dokploy?->base_url ?? '', PHP_URL_HOST),
            'server_id' => $project->server_id ?: 'local',
            'actor_id' => auth()->id(),
            'operation' => $enabled === null ? 'refresh' : 'set_enabled',
            'requested_enabled' => $enabled,
        ];

        try {
            $path = $this->resolvePath($project->maintenance_yml_file);
        } catch (MaintenanceException $exception) {
            Log::error('maintenance.operation.failed', $context + ['phase' => 'configuration', 'error' => $exception->getMessage()]);

            throw $exception;
        }

        $context['path'] = $path;
        Log::info('maintenance.operation.started', $context);

        // Projects sharing the same Traefik file must serialize their operations.
        $lock = Cache::lock($this->traefik->lockKey($project, $path), 180);

        if (! $lock->get()) {
            Log::warning('maintenance.operation.busy', $context);

            throw new MaintenanceException('Une opération de maintenance est déjà en cours sur ce fichier. Réessayez dans quelques instants.');
        }

        $phase = 'read';
        $diagnostics = [];

        try {
            $content = $this->traefik->read($project, $path, $context);
            $diagnostics['original'] = $this->fileDiagnostics($content);
            Log::info('maintenance.file.read', $context + $diagnostics);
            $phase = 'parse_flag';
            $actual = $this->parseFlag($content);

            if ($enabled !== null && $actual !== $enabled) {
                $wanted = preg_replace_callback(self::FLAG, fn (): string => '{{ $maintenance_enabled := '.(int) $enabled.' }}', $content, 1);
                $diagnostics['expected'] = $this->fileDiagnostics($wanted);
                $phase = 'write';
                Log::info('maintenance.file.write_requested', $context + $diagnostics);
                $this->traefik->write($project, $path, $wanted, $context);

                $verified = false;
                $phase = 'verify';

                for ($attempt = 0; $attempt < 3; $attempt++) {
                    if ($attempt > 0) {
                        usleep(1_000_000);
                    }

                    $reloaded = $this->traefik->read($project, $path, $context + ['attempt' => $attempt + 1]);

                    if ($this->normalize($reloaded) === $this->normalize($wanted)) {
                        $verified = true;
                        Log::info('maintenance.verification.succeeded', $context + ['attempt' => $attempt + 1]);
                        break;
                    }

                    $diagnostics['attempt'] = $attempt + 1;
                    $diagnostics['actual'] = $this->fileDiagnostics($reloaded);
                    $diagnostics['matches_original'] = $this->normalize($reloaded) === $this->normalize($content);
                    $diagnostics['flag_matches_requested'] = $diagnostics['actual']['flag_values'] === [(int) $enabled];
                    $diagnostics['first_differing_line'] = $this->firstDifferingLine($wanted, $reloaded);
                    Log::warning('maintenance.verification.mismatch', $context + $diagnostics);
                }

                if (! $verified) {
                    throw new MaintenanceException('L’écriture n’est pas confirmée : le fichier relu diffère du contenu envoyé. Actualisez le statut avant de réessayer.');
                }

                $actual = $enabled;
            } elseif ($enabled !== null) {
                Log::info('maintenance.file.already_in_requested_state', $context + ['maintenance_enabled' => $actual]);
            }

            $phase = 'save_status';
            $this->saveStatus($project, $path, [
                'maintenance_enabled' => $actual,
                'maintenance_checked_at' => now(),
                'maintenance_error' => null,
            ]);

            Log::info('maintenance.operation.completed', $context + [
                'maintenance_enabled' => $actual,
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            ]);

            return $actual;
        } catch (MaintenanceException $exception) {
            Log::error('maintenance.operation.failed', $context + [
                'phase' => $phase,
                'error' => $exception->getMessage(),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            ] + $diagnostics);

            $this->saveStatus($project, $path, [
                'maintenance_enabled' => null,
                'maintenance_checked_at' => null,
                'maintenance_error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function statusCacheKey(Project $project, string $path): string
    {
        // Changing connection credentials invalidates the status as well as changing the target file.
        return $this->traefik->lockKey($project, $path).':status:'.hash('sha256', $project->dokploy?->token ?? '');
    }

    private function saveStatus(Project $project, string $path, array $status): void
    {
        $project->update($status);
        Cache::put($this->statusCacheKey($project, $path), $status, now()->addMinutes(3));
    }

    private function parseFlag(string $content): bool
    {
        if (preg_match_all(self::FLAG, $content, $matches) !== 1) {
            throw new MaintenanceException('Variable maintenance_enabled absente ou ambiguë : le fichier doit contenir exactement un drapeau {{ $maintenance_enabled := 0 }} ou 1.');
        }

        return $matches[1][0] === '1';
    }

    private function normalize(string $content): string
    {
        return rtrim(str_replace("\r\n", "\n", $content), "\n");
    }

    private function fileDiagnostics(string $content): array
    {
        preg_match_all(self::FLAG, $content, $matches);
        $normalized = $this->normalize($content);

        return [
            'bytes' => strlen($content),
            'normalized_sha256' => hash('sha256', $normalized),
            'line_count' => count(explode("\n", $normalized)),
            'flag_count' => count($matches[1]),
            'flag_values' => array_map('intval', $matches[1]),
        ];
    }

    private function firstDifferingLine(string $expected, string $actual): ?int
    {
        $expectedLines = explode("\n", $this->normalize($expected));
        $actualLines = explode("\n", $this->normalize($actual));

        for ($line = 0; $line < max(count($expectedLines), count($actualLines)); $line++) {
            if (($expectedLines[$line] ?? null) !== ($actualLines[$line] ?? null)) {
                return $line + 1;
            }
        }

        return null;
    }
}
