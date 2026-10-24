<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DependencyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'repository' => $this->repository,
            'branch' => $this->branch,
            'commit_sha' => $this->commit_sha,
            'image' => $this->image,
            'digest' => $this->digest,
            'platform' => $this->platform,
            'language_version' => $this->language_version,
            'built_at' => $this->built_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'audits' => DependencyAuditResource::collection($this->whenLoaded('audits')),
        ];
    }
}
