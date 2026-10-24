<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\RotateTokenRequest;
use App\Http\Resources\IssuedTokenResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\TokenService;

class RotateTokenTool extends InfrastructureTool
{
    protected const REQUEST = RotateTokenRequest::class;

    public function __construct(private readonly TokenService $tokens) {}

    protected function resource(array $data, User $user): IssuedTokenResource
    {
        return new IssuedTokenResource($this->tokens->rotate($data['id'], $user));
    }
}
