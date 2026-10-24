<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_yml_file' => $this->maintenance_yml_file,
            'maintenance_enabled' => $this->maintenance_enabled,
            'maintenance_checked_at' => $this->maintenance_checked_at,
            'maintenance_error' => $this->maintenance_error,
        ];
    }
}
