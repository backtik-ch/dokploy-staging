<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'token_configured' => filled($this->resource->getRawOriginal('token')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
