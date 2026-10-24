<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\DeleteProjectRequest;
use App\Http\Resources\DeletedResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectService;

class DeleteProjectTool extends InfrastructureTool
{
    protected const REQUEST = DeleteProjectRequest::class;

    public function __construct(private readonly ProjectService $projects) {}

    protected function resource(array $data, User $user): DeletedResource
    {
        return new DeletedResource($this->projects->delete($data['id'], $user));
    }
}
