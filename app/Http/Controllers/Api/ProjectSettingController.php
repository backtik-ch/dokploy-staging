<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResetProjectSettingsRequest;
use App\Http\Requests\Api\ShowProjectSettingsRequest;
use App\Http\Requests\Api\UpdateProjectSettingsRequest;
use App\Http\Resources\ProjectResource;
use App\Services\Infrastructure\ProjectSettingsService;

class ProjectSettingController extends Controller
{
    public function __construct(private readonly ProjectSettingsService $settings) {}

    public function show(ShowProjectSettingsRequest $request): ProjectResource
    {
        $project = $this->settings->find($request->validated('project_id'), $request->user());

        return new ProjectResource($project);
    }

    public function update(UpdateProjectSettingsRequest $request): ProjectResource
    {
        $project = $this->settings->update(
            $request->validated('project_id'),
            $request->safe()->except('project_id'),
            $request->user(),
        );

        return new ProjectResource($project);
    }

    public function destroy(ResetProjectSettingsRequest $request): ProjectResource
    {
        $project = $this->settings->reset($request->validated('project_id'), $request->user());

        return new ProjectResource($project);
    }
}
