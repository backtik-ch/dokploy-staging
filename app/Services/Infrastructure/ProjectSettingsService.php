<?php

namespace App\Services\Infrastructure;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ProjectSettingsService
{
    public function __construct(private readonly ProjectService $projects) {}

    public function find(int $projectId, User $user): Project
    {
        return $this->projects->find($projectId, $user);
    }

    public function update(int $projectId, array $data, User $user): Project
    {
        return $this->projects->update($projectId, $data, $user);
    }

    public function reset(int $projectId, User $user): Project
    {
        ApiAccess::authorize($user, 'infra:write');
        ApiAccess::authorize($user, 'secrets:write');
        $project = Project::findOrFail($projectId);
        Gate::forUser($user)->authorize('update', $project);
        abort_if($project->stagings()->exists(), 409, 'Supprimez les stagings avant de réinitialiser leurs réglages.');
        $project->update($this->defaults());

        return $project->refresh()->loadCount('stagings');
    }

    private function defaults(): array
    {
        return [
            'dokploy_project_id' => null, 'github_id' => null, 'github_owner' => null,
            'github_repository' => null, 'github_token' => null, 'linked_repositories' => null,
            'compose_name_file' => null, 'domain_name' => null, 'environment_staging' => null,
            'extra_sub_domains' => null, 'wait_for_main_image' => true,
            'certificate_type' => 'letsencrypt', 'custom_cert_resolver' => 'infomaniak',
            'service_name' => 'server', 'service_port' => 80,
        ];
    }
}
