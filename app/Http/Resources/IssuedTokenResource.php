<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssuedTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...TokenResource::make($this->resource->accessToken)->resolve($request),
            'plain_text_token' => $this->resource->plainTextToken,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(201);
    }
}
