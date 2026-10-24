<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\DeleteTokenRequest;
use App\Http\Resources\DeletedResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\TokenService;

class DeleteTokenTool extends InfrastructureTool
{
    protected const REQUEST = DeleteTokenRequest::class;

    public function __construct(private readonly TokenService $tokens) {}

    protected function resource(array $data, User $user): DeletedResource
    {
        return new DeletedResource($this->tokens->delete($data['id'], $user));
    }
}
