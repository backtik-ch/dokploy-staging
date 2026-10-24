<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\UpdateStagingRequest;
use App\Http\Resources\StagingDeploymentResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\StagingService;
use Illuminate\Support\Arr;

class UpdateStagingTool extends InfrastructureTool
{
    protected const REQUEST = UpdateStagingRequest::class;

    public function __construct(private readonly StagingService $stagings) {}

    protected function resource(array $data, User $user): StagingDeploymentResource
    {
        return new StagingDeploymentResource($this->stagings->update($data['id'], Arr::except($data, 'id'), $user));
    }
}
