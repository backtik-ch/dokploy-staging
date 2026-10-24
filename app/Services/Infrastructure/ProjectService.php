<?php

namespace App\Services\Infrastructure;

use App\Models\Project;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ProjectService
{
    public function __construct(
        private readonly ConnectionService $connections,
        private readonly MaintenanceService $maintenance,
        private readonly RemoteOperation $remote,
    ) {}

    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        ApiAccess::authorize($user, 'infra:read');
        Gate::forUser($user)->authorize('viewAny', Project::class);
        $query = Project::query()->withCount('stagings')->orderBy('id');
        if (isset($filters['search'])) {
            $query->where('app_name', 'like', '%'.$filters['search'].'%');
        }
        if (isset($filters['dokploy_id'])) {
            $query->where('dokploy_id', $filters['dokploy_id']);
        }

        return $query->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
    }

    public function find(int $id, User $user): Project
    {
        $project = Project::findOrFail($id);
        Gate::forUser($user)->authorize('view', $project);

        return $project->loadCount('stagings');
    }

    public function create(array $data, User $user): Project
    {
        $this->authorizeConfiguration($data, $user);
        Gate::forUser($user)->authorize('create', Project::class);
        $this->connections->find($data['dokploy_id'], $user);

        return Project::create($data)->refresh()->loadCount('stagings');
    }

    public function update(int $id, array $data, User $user): Project
    {
        $this->authorizeConfiguration($data, $user);
        $project = Project::findOrFail($id);
        Gate::forUser($user)->authorize('update', $project);
        $project->fill($data);
        $this->ensureDestinationCanChange($project, $user);
        if (isset($data['dokploy_id'])) {
            $this->connections->find($data['dokploy_id'], $user);
        }
        $project->save();

        return $project->refresh()->loadCount('stagings');
    }

    public function delete(int $id, User $user): Project
    {
        ApiAccess::authorize($user, 'infra:write');
        $project = Project::findOrFail($id);
        Gate::forUser($user)->authorize('delete', $project);
        abort_if($project->stagings()->exists(), 409, 'Supprimez les stagings avant d’archiver le projet.');
        if (filled($project->maintenance_yml_file)) {
            abort_if($this->remote->run(fn () => $this->maintenance->refresh($project)), 409, 'Remettez le site en ligne avant d’archiver le projet.');
        }
        $project->delete();

        return $project;
    }

    private function authorizeConfiguration(array $data, User $user): void
    {
        ApiAccess::authorize($user, 'infra:write');
        if (array_intersect(['github_token', 'environment_staging'], array_keys($data))) {
            ApiAccess::authorize($user, 'secrets:write');
        }
        if (array_key_exists('maintenance_yml_file', $data)) {
            ApiAccess::authorize($user, 'maintenance:write');
        }
    }

    private function ensureDestinationCanChange(Project $project, User $user): void
    {
        $changed = array_keys($project->getDirty());
        if (array_intersect(['dokploy_id', 'dokploy_project_id', 'server_id'], $changed) && $project->stagings()->exists()) {
            abort(409, 'Supprimez les stagings avant de changer leur destination Dokploy.');
        }
        if (array_intersect(['maintenance_yml_file', 'dokploy_id', 'server_id'], $changed) && filled($project->getRawOriginal('maintenance_yml_file'))) {
            ApiAccess::authorize($user, 'maintenance:write');
            $previous = $project->fresh();
            abort_if($this->remote->run(fn () => $this->maintenance->refresh($previous)), 409, 'Remettez le site en ligne avant de changer la destination ou le fichier de maintenance.');
        }
    }
}
