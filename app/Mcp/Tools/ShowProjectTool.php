<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectService;

class ShowProjectTool extends InfrastructureTool
{
    protected const REQUEST = ShowProjectRequest::class;

    public function __construct(private readonly ProjectService $projects) {}

    protected function resource(array $data, User $user): ProjectResource
    {
        return new ProjectResource($this->projects->find($data['id'], $user));
    }
}
