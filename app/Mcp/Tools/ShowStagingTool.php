<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowStagingRequest;
use App\Http\Resources\StagingResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\StagingService;

class ShowStagingTool extends InfrastructureTool
{
    protected const REQUEST = ShowStagingRequest::class;

    public function __construct(private readonly StagingService $stagings) {}

    protected function resource(array $data, User $user): StagingResource
    {
        return new StagingResource($this->stagings->find($data['id'], $user));
    }
}
