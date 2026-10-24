<?php

namespace App\Services\Infrastructure;

use App\Models\Dokploy;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ConnectionService
{
    public function __construct(
        private readonly MaintenanceService $maintenance,
        private readonly RemoteOperation $remote,
    ) {}

    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        ApiAccess::authorize($user, 'infra:read');
        Gate::forUser($user)->authorize('viewAny', Dokploy::class);

        return Dokploy::orderBy('id')
            ->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
    }

    public function find(int $id, User $user): Dokploy
    {
        $connection = Dokploy::findOrFail($id);
        Gate::forUser($user)->authorize('view', $connection);

        return $connection;
    }

    public function create(array $data, User $user): Dokploy
    {
        ApiAccess::authorize($user, 'infra:write');
        ApiAccess::authorize($user, 'secrets:write');
        Gate::forUser($user)->authorize('create', Dokploy::class);

        return Dokploy::create($data)->refresh();
    }

    public function update(int $id, array $data, User $user): Dokploy
    {
        ApiAccess::authorize($user, 'infra:write');
        if (array_key_exists('token', $data)) {
            ApiAccess::authorize($user, 'secrets:write');
        }
        $connection = Dokploy::findOrFail($id);
        Gate::forUser($user)->authorize('update', $connection);
        $connection->fill($data);
        if ($connection->isDirty('base_url')) {
            $this->ensureDestinationCanChange($connection, $user);
        }
        $connection->save();

        return $connection->refresh();
    }

    public function delete(int $id, User $user): Dokploy
    {
        ApiAccess::authorize($user, 'infra:write');
        $connection = Dokploy::findOrFail($id);
        Gate::forUser($user)->authorize('delete', $connection);
        abort_if($connection->projects()->exists(), 409, 'Archivez les projets avant de supprimer leur connexion Dokploy.');
        $connection->delete();

        return $connection;
    }

    private function ensureDestinationCanChange(Dokploy $connection, User $user): void
    {
        abort_if($connection->projects()->whereHas('stagings')->exists(), 409, 'Supprimez les stagings avant de changer l’instance Dokploy.');
        foreach ($connection->fresh()->projects()->whereNotNull('maintenance_yml_file')->get() as $project) {
            ApiAccess::authorize($user, 'maintenance:write');
            abort_if($this->remote->run(fn () => $this->maintenance->refresh($project)), 409, 'Remettez les sites en ligne avant de changer leur instance Dokploy.');
        }
    }
}
