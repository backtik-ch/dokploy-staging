<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DependencyAuditFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dependency_audit_id' => $this->dependency_audit_id,
            'dependency_report_package_id' => $this->dependency_report_package_id,
            'severity' => $this->severity,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
