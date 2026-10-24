<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\UpdateConnectionRequest;
use App\Http\Resources\ConnectionResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ConnectionService;
use Illuminate\Support\Arr;

class UpdateConnectionTool extends InfrastructureTool
{
    protected const REQUEST = UpdateConnectionRequest::class;

    public function __construct(private readonly ConnectionService $connections) {}

    protected function resource(array $data, User $user): ConnectionResource
    {
        return new ConnectionResource($this->connections->update($data['id'], Arr::except($data, 'id'), $user));
    }
}
