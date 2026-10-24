<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\UpdateTokenRequest;
use App\Http\Resources\TokenResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\TokenService;
use Illuminate\Support\Arr;

class UpdateTokenTool extends InfrastructureTool
{
    protected const REQUEST = UpdateTokenRequest::class;

    public function __construct(private readonly TokenService $tokens) {}

    protected function resource(array $data, User $user): TokenResource
    {
        return new TokenResource($this->tokens->update($data['id'], Arr::except($data, 'id'), $user));
    }
}
