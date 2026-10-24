<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\RedeployStagingRequest;
use App\Http\Resources\StagingDeploymentResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\StagingService;

class RedeployStagingTool extends InfrastructureTool
{
    protected const REQUEST = RedeployStagingRequest::class;

    public function __construct(private readonly StagingService $stagings) {}

    protected function resource(array $data, User $user): StagingDeploymentResource
    {
        return new StagingDeploymentResource($this->stagings->redeploy($data['id'], $user));
    }
}
