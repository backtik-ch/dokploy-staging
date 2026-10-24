<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\UpdateProjectSettingsRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectSettingsService;
use Illuminate\Support\Arr;

class UpdateProjectSettingsTool extends InfrastructureTool
{
    protected const REQUEST = UpdateProjectSettingsRequest::class;

    public function __construct(private readonly ProjectSettingsService $settings) {}

    protected function resource(array $data, User $user): ProjectResource
    {
        return new ProjectResource($this->settings->update($data['project_id'], Arr::except($data, 'project_id'), $user));
    }
}
