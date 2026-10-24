<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\DeleteMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectMaintenanceService;

class DeleteMaintenanceTool extends InfrastructureTool
{
    protected const REQUEST = DeleteMaintenanceRequest::class;

    public function __construct(private readonly ProjectMaintenanceService $maintenance) {}

    protected function resource(array $data, User $user): MaintenanceResource
    {
        return new MaintenanceResource($this->maintenance->delete($data['project_id'], $user));
    }
}
