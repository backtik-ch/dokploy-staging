<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class LogEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return Arr::only($this->resource, [
            'id', 'timestamp', 'datetime', 'time', 'level', 'project', 'service', 'environment',
            'message', 'raw', 'pretty', 'labels', 'metadata', 'stream',
        ]);
    }
}
