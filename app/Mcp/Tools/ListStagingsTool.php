<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListStagingsRequest;
use App\Http\Resources\StagingResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\StagingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListStagingsTool extends InfrastructureTool
{
    protected const REQUEST = ListStagingsRequest::class;

    public function __construct(private readonly StagingService $stagings) {}

    protected function resource(array $data, User $user): AnonymousResourceCollection
    {
        return StagingResource::collection($this->stagings->paginate($data, $user));
    }
}
