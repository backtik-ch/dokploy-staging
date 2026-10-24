<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeletedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'deleted' => true];
    }
}
