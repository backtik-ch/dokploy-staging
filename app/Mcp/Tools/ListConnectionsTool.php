<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListConnectionsRequest;
use App\Http\Resources\ConnectionResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ConnectionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListConnectionsTool extends InfrastructureTool
{
    protected const REQUEST = ListConnectionsRequest::class;

    public function __construct(private readonly ConnectionService $connections) {}

    protected function resource(array $data, User $user): AnonymousResourceCollection
    {
        return ConnectionResource::collection($this->connections->paginate($data, $user));
    }
}
