<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ResetProjectSettingsRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectSettingsService;

class ResetProjectSettingsTool extends InfrastructureTool
{
    protected const REQUEST = ResetProjectSettingsRequest::class;

    public function __construct(private readonly ProjectSettingsService $settings) {}

    protected function resource(array $data, User $user): ProjectResource
    {
        return new ProjectResource($this->settings->reset($data['project_id'], $user));
    }
}
