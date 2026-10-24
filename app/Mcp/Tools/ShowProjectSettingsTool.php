<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowProjectSettingsRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectSettingsService;

class ShowProjectSettingsTool extends InfrastructureTool
{
    protected const REQUEST = ShowProjectSettingsRequest::class;

    public function __construct(private readonly ProjectSettingsService $settings) {}

    protected function resource(array $data, User $user): ProjectResource
    {
        return new ProjectResource($this->settings->find($data['project_id'], $user));
    }
}
