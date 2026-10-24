<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowConnectionRequest;
use App\Http\Resources\ConnectionResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ConnectionService;

class ShowConnectionTool extends InfrastructureTool
{
    protected const REQUEST = ShowConnectionRequest::class;

    public function __construct(private readonly ConnectionService $connections) {}

    protected function resource(array $data, User $user): ConnectionResource
    {
        return new ConnectionResource($this->connections->find($data['id'], $user));
    }
}
