<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowTokenRequest;
use App\Http\Resources\TokenResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\TokenService;

class ShowTokenTool extends InfrastructureTool
{
    protected const REQUEST = ShowTokenRequest::class;

    public function __construct(private readonly TokenService $tokens) {}

    protected function resource(array $data, User $user): TokenResource
    {
        return new TokenResource($this->tokens->find($data['id'], $user));
    }
}
