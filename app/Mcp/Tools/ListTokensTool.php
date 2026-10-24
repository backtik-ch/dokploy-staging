<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListTokensRequest;
use App\Http\Resources\TokenResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\TokenService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTokensTool extends InfrastructureTool
{
    protected const REQUEST = ListTokensRequest::class;

    public function __construct(private readonly TokenService $tokens) {}

    protected function resource(array $data, User $user): AnonymousResourceCollection
    {
        return TokenResource::collection($this->tokens->paginate($data, $user));
    }
}
