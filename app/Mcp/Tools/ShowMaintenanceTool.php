<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectMaintenanceService;

class ShowMaintenanceTool extends InfrastructureTool
{
    protected const REQUEST = ShowMaintenanceRequest::class;

    public function __construct(private readonly ProjectMaintenanceService $maintenance) {}

    protected function resource(array $data, User $user): MaintenanceResource
    {
        return new MaintenanceResource($this->maintenance->find($data['project_id'], $user));
    }
}
