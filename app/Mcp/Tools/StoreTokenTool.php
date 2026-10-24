<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\StoreTokenRequest;
use App\Http\Resources\IssuedTokenResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\TokenService;

class StoreTokenTool extends InfrastructureTool
{
    protected const REQUEST = StoreTokenRequest::class;

    public function __construct(private readonly TokenService $tokens) {}

    protected function resource(array $data, User $user): IssuedTokenResource
    {
        return new IssuedTokenResource($this->tokens->create($data, $user));
    }
}
