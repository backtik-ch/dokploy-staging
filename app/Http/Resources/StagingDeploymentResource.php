<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StagingDeploymentResource extends StagingResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(202);
    }
}
