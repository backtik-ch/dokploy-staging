<?php

namespace App\Services;

use App\Exceptions\MaintenanceException;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

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
        $path = $this->resolvePath($project->maintenance_yml_file);
        $project->load('dokploy');
        // Projects sharing the same Traefik file must serialize their operations.
        $lock = Cache::lock($this->traefik->lockKey($project, $path), 180);

        if (! $lock->get()) {
            throw new MaintenanceException('Une opération de maintenance est déjà en cours sur ce fichier. Réessayez dans quelques instants.');
        }

        try {
            $content = $this->traefik->read($project, $path);
            $actual = $this->parseFlag($content);

            if ($enabled !== null && $actual !== $enabled) {
                $wanted = preg_replace_callback(self::FLAG, fn (): string => '{{ $maintenance_enabled := '.(int) $enabled.' }}', $content, 1);
                $this->traefik->write($project, $path, $wanted);

                $verified = false;

                for ($attempt = 0; $attempt < 3; $attempt++) {
                    if ($attempt > 0) {
                        usleep(1_000_000);
                    }

                    $reloaded = $this->traefik->read($project, $path);

                    if ($this->normalize($reloaded) === $this->normalize($wanted)) {
                        $verified = true;
                        break;
                    }
                }

                if (! $verified) {
                    throw new MaintenanceException('L’écriture n’est pas confirmée : le fichier relu diffère du contenu envoyé. Actualisez le statut avant de réessayer.');
                }

                $actual = $enabled;
            }

            $this->saveStatus($project, $path, [
                'maintenance_enabled' => $actual,
                'maintenance_checked_at' => now(),
                'maintenance_error' => null,
            ]);

            return $actual;
        } catch (MaintenanceException $exception) {
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
}
