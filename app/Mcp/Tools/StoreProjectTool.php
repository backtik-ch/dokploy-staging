<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectService;

class StoreProjectTool extends InfrastructureTool
{
    protected const REQUEST = StoreProjectRequest::class;

    public function __construct(private readonly ProjectService $projects) {}

    protected function resource(array $data, User $user): ProjectResource
    {
        return new ProjectResource($this->projects->create($data, $user));
    }
}
