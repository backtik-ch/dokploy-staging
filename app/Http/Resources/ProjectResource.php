<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'app_name' => $this->app_name,
            'dokploy_id' => $this->dokploy_id,
            'server_id' => $this->server_id,
            'dokploy_project_id' => $this->dokploy_project_id,
            'github_id' => $this->github_id,
            'github_owner' => $this->github_owner,
            'github_repository' => $this->github_repository,
            'linked_repositories' => $this->linked_repositories,
            'compose_name_file' => $this->compose_name_file,
            'domain_name' => $this->domain_name,
            'extra_sub_domains' => $this->extra_sub_domains,
            'wait_for_main_image' => $this->wait_for_main_image,
            'certificate_type' => $this->certificate_type,
            'custom_cert_resolver' => $this->custom_cert_resolver,
            'service_name' => $this->service_name,
            'service_port' => $this->service_port,
            'maintenance_yml_file' => $this->maintenance_yml_file,
            'maintenance_enabled' => $this->maintenance_enabled,
            'maintenance_checked_at' => $this->maintenance_checked_at,
            'maintenance_error' => $this->maintenance_error,
            'github_token_configured' => filled($this->resource->getRawOriginal('github_token')),
            'environment_staging_configured' => filled($this->resource->getRawOriginal('environment_staging')),
            'staging_configured' => $this->resource->hasStagingConfiguration(),
            'stagings_count' => $this->whenCounted('stagings'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
