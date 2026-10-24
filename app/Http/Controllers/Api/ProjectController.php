<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteProjectRequest;
use App\Http\Requests\Api\ListProjectsRequest;
use App\Http\Requests\Api\ShowProjectRequest;
use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Requests\Api\UpdateProjectRequest;
use App\Http\Resources\DeletedResource;
use App\Http\Resources\ProjectResource;
use App\Services\Infrastructure\ProjectService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(ListProjectsRequest $request): AnonymousResourceCollection
    {
        $projects = $this->projects->paginate($request->validated(), $request->user());

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): ProjectResource
    {
        $project = $this->projects->create($request->validated(), $request->user());

        return new ProjectResource($project);
    }

    public function show(ShowProjectRequest $request): ProjectResource
    {
        $project = $this->projects->find($request->validated('id'), $request->user());

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request): ProjectResource
    {
        $project = $this->projects->update(
            $request->validated('id'),
            $request->safe()->except('id'),
            $request->user(),
        );

        return new ProjectResource($project);
    }

    public function destroy(DeleteProjectRequest $request): DeletedResource
    {
        $project = $this->projects->delete($request->validated('id'), $request->user());

        return new DeletedResource($project);
    }
}
