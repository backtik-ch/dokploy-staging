<?php

namespace App\Http\Requests\Api;

use App\Rules\MaintenanceFile;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends InfrastructureRequest
{
    public const OPERATION = 'projects_update';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return [
            'id' => self::ID_RULES,
            'app_name' => ['sometimes', 'string', 'max:255'],
            'dokploy_id' => ['sometimes', 'integer', Rule::exists('dokploys', 'id')->whereNull('deleted_at')],
            'server_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'maintenance_yml_file' => ['sometimes', 'nullable', 'string', 'max:255', new MaintenanceFile],
            'github_token' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'environment_staging' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'wait_for_main_image' => ['sometimes', 'boolean'],
            'service_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'certificate_type' => ['sometimes', Rule::in(['letsencrypt', 'custom'])],
            'extra_sub_domains' => ['sometimes', 'nullable', 'array', 'list', 'max:50'],
            'extra_sub_domains.*' => ['string', 'max:255'],
            'linked_repositories' => ['sometimes', 'nullable', 'array', 'list', 'max:50'],
            'linked_repositories.*' => ['array:label,owner,repository,branch_placeholder,wait_for_image'],
            'linked_repositories.*.label' => ['required', 'string', 'max:255'],
            'linked_repositories.*.owner' => ['required', 'string', 'max:255'],
            'linked_repositories.*.repository' => ['required', 'string', 'max:255'],
            'linked_repositories.*.branch_placeholder' => ['required', 'string', 'max:255', 'distinct', 'regex:/^[A-Z][A-Z0-9_]*$/D'],
            'linked_repositories.*.wait_for_image' => ['sometimes', 'boolean'],
            'dokploy_project_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'github_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'github_owner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'github_repository' => ['sometimes', 'nullable', 'string', 'max:255'],
            'compose_name_file' => ['sometimes', 'nullable', 'string', 'max:255'],
            'domain_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'custom_cert_resolver' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
