<?php

namespace App\Http\Requests\Api;

class ListProjectsRequest extends InfrastructureRequest
{
    public const OPERATION = 'projects_list';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return [...$this->paginationRules(), 'search' => ['sometimes', 'string', 'max:255'], 'dokploy_id' => ['sometimes', 'integer', 'min:1']];
    }
}
