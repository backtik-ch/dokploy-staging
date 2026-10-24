<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StagingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'pr_number' => $this->pr_number,
            'staging_reference' => $this->pr_number,
            'branch' => $this->branch,
            'selected_branches' => $this->selected_branches,
            'compose_id' => $this->compose_id,
            'environment_id' => $this->environment_id,
            'deploy_status' => $this->deploy_status,
            'missing_images' => $this->missing_images,
            'deploy_requested_at' => $this->deploy_requested_at,
            'deploy_checked_at' => $this->deploy_checked_at,
            'deploy_started_at' => $this->deploy_started_at,
            'deploy_error' => filled($this->deploy_error) ? 'Le déploiement a échoué. Consultez les logs du serveur.' : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
