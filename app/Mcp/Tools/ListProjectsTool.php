<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListProjectsRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ProjectService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListProjectsTool extends InfrastructureTool
{
    protected const REQUEST = ListProjectsRequest::class;

    public function __construct(private readonly ProjectService $projects) {}

    protected function resource(array $data, User $user): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->projects->paginate($data, $user));
    }
}
