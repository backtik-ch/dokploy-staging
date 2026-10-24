<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DependencyPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dependency_report_id' => $this->dependency_report_id,
            'name' => $this->name,
            'manager' => $this->manager,
            'path' => $this->path,
            'required' => $this->resource->getAttribute('required'),
            'locked' => $this->locked,
            'installed' => $this->installed,
            'locked_reference' => $this->locked_reference,
            'installed_reference' => $this->installed_reference,
            'lock_observed' => $this->lock_observed,
            'installation_observed' => $this->installation_observed,
            'dev' => $this->dev,
            'direct' => $this->direct,
            'audited_at' => $this->audited_at,
            'audit_findings' => DependencyAuditFindingResource::collection($this->whenLoaded('auditFindings')),
        ];
    }
}
