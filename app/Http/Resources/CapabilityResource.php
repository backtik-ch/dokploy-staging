<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CapabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->resource['version'],
            'abilities' => $this->resource['abilities'],
            'operations' => $this->resource['operations'],
            'logs_configured' => $this->resource['logs_configured'],
        ];
    }
}
