<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteTokenRequest;
use App\Http\Requests\Api\ListTokensRequest;
use App\Http\Requests\Api\RotateTokenRequest;
use App\Http\Requests\Api\ShowTokenRequest;
use App\Http\Requests\Api\StoreTokenRequest;
use App\Http\Requests\Api\UpdateTokenRequest;
use App\Http\Resources\DeletedResource;
use App\Http\Resources\IssuedTokenResource;
use App\Http\Resources\TokenResource;
use App\Services\Infrastructure\TokenService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TokenController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function index(ListTokensRequest $request): AnonymousResourceCollection
    {
        $tokens = $this->tokens->paginate($request->validated(), $request->user());

        return TokenResource::collection($tokens);
    }

    public function store(StoreTokenRequest $request): IssuedTokenResource
    {
        $token = $this->tokens->create($request->validated(), $request->user());

        return new IssuedTokenResource($token);
    }

    public function show(ShowTokenRequest $request): TokenResource
    {
        $token = $this->tokens->find($request->validated('id'), $request->user());

        return new TokenResource($token);
    }

    public function update(UpdateTokenRequest $request): TokenResource
    {
        $token = $this->tokens->update(
            $request->validated('id'),
            $request->safe()->except('id'),
            $request->user(),
        );

        return new TokenResource($token);
    }

    public function destroy(DeleteTokenRequest $request): DeletedResource
    {
        $token = $this->tokens->delete($request->validated('id'), $request->user());

        return new DeletedResource($token);
    }

    public function rotate(RotateTokenRequest $request): IssuedTokenResource
    {
        $token = $this->tokens->rotate($request->validated('id'), $request->user());

        return new IssuedTokenResource($token);
    }
}
