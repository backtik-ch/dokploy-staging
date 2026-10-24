<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteMaintenanceRequest;
use App\Http\Requests\Api\ShowMaintenanceRequest;
use App\Http\Requests\Api\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Services\Infrastructure\ProjectMaintenanceService;

class MaintenanceController extends Controller
{
    public function __construct(private readonly ProjectMaintenanceService $maintenance) {}

    public function show(ShowMaintenanceRequest $request): MaintenanceResource
    {
        $project = $this->maintenance->find($request->validated('project_id'), $request->user());

        return new MaintenanceResource($project);
    }

    public function update(UpdateMaintenanceRequest $request): MaintenanceResource
    {
        $project = $this->maintenance->update(
            $request->validated('project_id'),
            $request->safe()->except('project_id'),
            $request->user(),
        );

        return new MaintenanceResource($project);
    }

    public function destroy(DeleteMaintenanceRequest $request): MaintenanceResource
    {
        $project = $this->maintenance->delete($request->validated('project_id'), $request->user());

        return new MaintenanceResource($project);
    }
}
