<?php

namespace App\Http\Requests\Api;

class ListStagingsRequest extends InfrastructureRequest
{
    public const OPERATION = 'stagings_list';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return [...$this->paginationRules(), 'project_id' => ['sometimes', 'integer', 'min:1'], 'deploy_status' => ['sometimes', 'string', 'max:255'], 'search' => ['sometimes', 'string', 'max:255']];
    }
}
