<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectService;
use Illuminate\Support\Arr;

class UpdateProjectTool extends InfrastructureTool
{
    protected const REQUEST = UpdateProjectRequest::class;

    public function __construct(private readonly ProjectService $projects) {}

    protected function resource(array $data, User $user): ProjectResource
    {
        return new ProjectResource($this->projects->update($data['id'], Arr::except($data, 'id'), $user));
    }
}
