<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\StoreStagingRequest;
use App\Http\Resources\StagingDeploymentResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\StagingService;
use Illuminate\Support\Arr;

class StoreStagingTool extends InfrastructureTool
{
    protected const REQUEST = StoreStagingRequest::class;

    public function __construct(private readonly StagingService $stagings) {}

    protected function resource(array $data, User $user): StagingDeploymentResource
    {
        return new StagingDeploymentResource($this->stagings->create($data['project_id'], Arr::except($data, 'project_id'), $user));
    }
}
