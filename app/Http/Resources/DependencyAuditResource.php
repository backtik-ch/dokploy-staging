<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DependencyAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dependency_report_id' => $this->dependency_report_id,
            'manager' => $this->manager,
            'attempted_at' => $this->attempted_at,
            'audited_at' => $this->audited_at,
            'error' => $this->error,
        ];
    }
}
