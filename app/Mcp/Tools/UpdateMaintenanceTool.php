<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectMaintenanceService;
use Illuminate\Support\Arr;

class UpdateMaintenanceTool extends InfrastructureTool
{
    protected const REQUEST = UpdateMaintenanceRequest::class;

    public function __construct(private readonly ProjectMaintenanceService $maintenance) {}

    protected function resource(array $data, User $user): MaintenanceResource
    {
        return new MaintenanceResource($this->maintenance->update($data['project_id'], Arr::except($data, 'project_id'), $user));
    }
}
