<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\DeleteConnectionRequest;
use App\Http\Resources\DeletedResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\ConnectionService;

class DeleteConnectionTool extends InfrastructureTool
{
    protected const REQUEST = DeleteConnectionRequest::class;

    public function __construct(private readonly ConnectionService $connections) {}

    protected function resource(array $data, User $user): DeletedResource
    {
        return new DeletedResource($this->connections->delete($data['id'], $user));
    }
}
