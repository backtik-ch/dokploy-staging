<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\DeleteStagingRequest;
use App\Http\Resources\DeletedResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\StagingService;

class DeleteStagingTool extends InfrastructureTool
{
    protected const REQUEST = DeleteStagingRequest::class;

    public function __construct(private readonly StagingService $stagings) {}

    protected function resource(array $data, User $user): DeletedResource
    {
        return new DeletedResource($this->stagings->delete($data['id'], $user));
    }
}
