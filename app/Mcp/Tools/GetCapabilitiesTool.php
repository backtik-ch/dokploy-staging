<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\GetCapabilitiesRequest;
use App\Http\Resources\CapabilityResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\CapabilityService;

class GetCapabilitiesTool extends InfrastructureTool
{
    protected const REQUEST = GetCapabilitiesRequest::class;

    public function __construct(private readonly CapabilityService $capabilities) {}

    protected function resource(array $data, User $user): CapabilityResource
    {
        return new CapabilityResource($this->capabilities->forUser($user));
    }
}
