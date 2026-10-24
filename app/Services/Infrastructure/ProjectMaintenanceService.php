<?php

namespace App\Services\Infrastructure;

use App\Models\Project;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectMaintenanceService
{
    public function __construct(
        private readonly MaintenanceService $maintenance,
        private readonly RemoteOperation $remote,
    ) {}

    public function find(int $projectId, User $user): Project
    {
        ApiAccess::authorize($user, 'infra:read');
        $project = Project::findOrFail($projectId);
        Gate::forUser($user)->authorize('view', $project);
        $this->refreshStatus($project);

        return $project->refresh();
    }

    public function update(int $projectId, array $data, User $user): Project
    {
        ApiAccess::authorize($user, 'maintenance:write');
        $project = Project::findOrFail($projectId);
        Gate::forUser($user)->authorize('update', $project);
        if (isset($data['maintenance_yml_file'])) {
            $this->configureFile($project, $data['maintenance_yml_file']);
        }
        if (array_key_exists('enabled', $data)) {
            $this->setEnabled($project, (bool) $data['enabled']);
        } else {
            $this->refreshStatus($project);
        }

        return $project->refresh();
    }

    public function delete(int $projectId, User $user): Project
    {
        ApiAccess::authorize($user, 'maintenance:write');
        $project = Project::findOrFail($projectId);
        Gate::forUser($user)->authorize('update', $project);
        if (filled($project->maintenance_yml_file)) {
            $this->remote->run(fn () => $this->maintenance->setEnabled($project, false));
        }
        $project->update([
            'maintenance_yml_file' => null, 'maintenance_enabled' => null,
            'maintenance_checked_at' => null, 'maintenance_error' => null,
        ]);

        return $project->refresh();
    }

    private function configureFile(Project $project, string $file): void
    {
        if ($file === $project->maintenance_yml_file) {
            return;
        }
        if (filled($project->maintenance_yml_file)) {
            abort_if($this->remote->run(fn () => $this->maintenance->refresh($project)), 409, 'Remettez le site en ligne avant de changer le fichier de maintenance.');
        }
        $project->update(['maintenance_yml_file' => $file]);
    }

    private function setEnabled(Project $project, bool $enabled): void
    {
        if (blank($project->maintenance_yml_file)) {
            throw ValidationException::withMessages(['maintenance_yml_file' => 'Configurez le fichier de maintenance avant de modifier son état.']);
        }
        $this->remote->run(fn () => $this->maintenance->setEnabled($project, $enabled));
    }

    private function refreshStatus(Project $project): void
    {
        if (filled($project->maintenance_yml_file)) {
            $this->remote->run(fn () => $this->maintenance->refresh($project));
        }
    }
}
