<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteStagingRequest;
use App\Http\Requests\Api\ListStagingsRequest;
use App\Http\Requests\Api\RedeployStagingRequest;
use App\Http\Requests\Api\ShowStagingRequest;
use App\Http\Requests\Api\StoreStagingRequest;
use App\Http\Requests\Api\UpdateStagingRequest;
use App\Http\Resources\DeletedResource;
use App\Http\Resources\StagingDeploymentResource;
use App\Http\Resources\StagingResource;
use App\Services\Infrastructure\StagingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StagingController extends Controller
{
    public function __construct(private readonly StagingService $stagings) {}

    public function index(ListStagingsRequest $request): AnonymousResourceCollection
    {
        $stagings = $this->stagings->paginate($request->validated(), $request->user());

        return StagingResource::collection($stagings);
    }

    public function store(StoreStagingRequest $request): StagingDeploymentResource
    {
        $staging = $this->stagings->create(
            $request->validated('project_id'),
            $request->safe()->except('project_id'),
            $request->user(),
        );

        return new StagingDeploymentResource($staging);
    }

    public function show(ShowStagingRequest $request): StagingResource
    {
        $staging = $this->stagings->find($request->validated('id'), $request->user());

        return new StagingResource($staging);
    }

    public function update(UpdateStagingRequest $request): StagingDeploymentResource
    {
        $staging = $this->stagings->update(
            $request->validated('id'),
            $request->safe()->except('id'),
            $request->user(),
        );

        return new StagingDeploymentResource($staging);
    }

    public function destroy(DeleteStagingRequest $request): DeletedResource
    {
        $staging = $this->stagings->delete($request->validated('id'), $request->user());

        return new DeletedResource($staging);
    }

    public function redeploy(RedeployStagingRequest $request): StagingDeploymentResource
    {
        $staging = $this->stagings->redeploy($request->validated('id'), $request->user());

        return new StagingDeploymentResource($staging);
    }
}
