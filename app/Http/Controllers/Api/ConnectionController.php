<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteConnectionRequest;
use App\Http\Requests\Api\ListConnectionsRequest;
use App\Http\Requests\Api\ShowConnectionRequest;
use App\Http\Requests\Api\StoreConnectionRequest;
use App\Http\Requests\Api\UpdateConnectionRequest;
use App\Http\Resources\ConnectionResource;
use App\Http\Resources\DeletedResource;
use App\Services\Infrastructure\ConnectionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConnectionController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function index(ListConnectionsRequest $request): AnonymousResourceCollection
    {
        $connections = $this->connections->paginate($request->validated(), $request->user());

        return ConnectionResource::collection($connections);
    }

    public function store(StoreConnectionRequest $request): ConnectionResource
    {
        $connection = $this->connections->create($request->validated(), $request->user());

        return new ConnectionResource($connection);
    }

    public function show(ShowConnectionRequest $request): ConnectionResource
    {
        $connection = $this->connections->find($request->validated('id'), $request->user());

        return new ConnectionResource($connection);
    }

    public function update(UpdateConnectionRequest $request): ConnectionResource
    {
        $connection = $this->connections->update(
            $request->validated('id'),
            $request->safe()->except('id'),
            $request->user(),
        );

        return new ConnectionResource($connection);
    }

    public function destroy(DeleteConnectionRequest $request): DeletedResource
    {
        $connection = $this->connections->delete($request->validated('id'), $request->user());

        return new DeletedResource($connection);
    }
}
