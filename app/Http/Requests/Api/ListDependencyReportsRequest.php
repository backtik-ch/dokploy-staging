<?php

namespace App\Http\Requests\Api;

class ListDependencyReportsRequest extends InfrastructureRequest
{
    public const OPERATION = 'dependencies_list';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES, ...$this->paginationRules()];
    }
}
