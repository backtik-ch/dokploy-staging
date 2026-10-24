<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetCapabilitiesRequest;
use App\Http\Resources\CapabilityResource;
use App\Http\Resources\OpenApiResource;
use App\Services\Infrastructure\CapabilityService;
use App\Services\Infrastructure\OpenApi;

class CapabilityController extends Controller
{
    public function __construct(private readonly CapabilityService $capabilities) {}

    public function index(GetCapabilitiesRequest $request): CapabilityResource
    {
        $capabilities = $this->capabilities->forUser($request->user());

        return new CapabilityResource($capabilities);
    }

    public function openapi(): OpenApiResource
    {
        return new OpenApiResource(OpenApi::document());
    }
}
