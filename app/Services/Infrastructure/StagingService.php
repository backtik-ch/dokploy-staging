<?php

namespace App\Services\Infrastructure;

use App\Models\Project;
use App\Models\Staging;
use App\Models\User;
use App\Services\DeployService;
use App\Services\StagingDeletionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class StagingService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly DeployService $deployments,
        private readonly StagingDeletionService $deletions,
        private readonly RemoteOperation $remote,
    ) {}

    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        ApiAccess::authorize($user, 'infra:read');
        Gate::forUser($user)->authorize('viewAny', Staging::class);
        $query = Staging::query()->whereHas('project')->orderBy('id');
        if (isset($filters['project_id'])) {
            $this->projects->find($filters['project_id'], $user);
            $query->where('project_id', $filters['project_id']);
        }
        if (isset($filters['deploy_status'])) {
            $query->where('deploy_status', $filters['deploy_status']);
        }
        if (isset($filters['search'])) {
            $query->where(fn ($query) => $query->where('pr_number', 'like', '%'.$filters['search'].'%')->orWhere('branch', 'like', '%'.$filters['search'].'%'));
        }

        return $query->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
    }

    public function find(int $id, User $user): Staging
    {
        $staging = $this->findWithProject($id, $user);
        Gate::forUser($user)->authorize('view', $staging);

        return $staging;
    }

    public function create(int $projectId, array $data, User $user): Staging
    {
        ApiAccess::authorize($user, 'stagings:deploy');
        $project = Project::findOrFail($projectId);
        Gate::forUser($user)->authorize('update', $project);
        Gate::forUser($user)->authorize('create', Staging::class);
        $existing = $project->stagings()->where('pr_number', $data['staging_reference'])->first();
        if ($existing) {
            Gate::forUser($user)->authorize('update', $existing);
        }

        return $this->remote->run(fn () => $this->deployments->deploy(
            $project, 'create', $data['staging_reference'], $data['branch'], $data['selected_branches'] ?? null, true,
        ));
    }

    public function update(int $id, array $data, User $user): Staging
    {
        ApiAccess::authorize($user, 'stagings:deploy');
        $staging = $this->findWithProject($id, $user);
        Gate::forUser($user)->authorize('update', $staging);
        Gate::forUser($user)->authorize('update', $staging->project);

        return $this->remote->run(fn () => $this->deployments->deploy(
            $staging->project, 'create', (string) $staging->pr_number, $data['branch'] ?? $staging->branch,
            $data['selected_branches'] ?? $staging->selected_branches ?? [], true,
        ));
    }

    public function redeploy(int $id, User $user): Staging
    {
        return $this->update($id, [], $user);
    }

    public function delete(int $id, User $user): Staging
    {
        ApiAccess::authorize($user, 'stagings:delete');
        $staging = $this->findWithProject($id, $user);
        Gate::forUser($user)->authorize('delete', $staging);
        $this->remote->run(fn () => $this->deletions->delete($staging));

        return $staging;
    }

    private function findWithProject(int $id, User $user): Staging
    {
        $staging = Staging::with('project')->whereHas('project')->findOrFail($id);
        Gate::forUser($user)->authorize('view', $staging->project);

        return $staging;
    }
}
