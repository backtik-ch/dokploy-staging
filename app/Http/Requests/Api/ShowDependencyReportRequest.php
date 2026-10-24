<?php

namespace App\Http\Requests\Api;

class ShowDependencyReportRequest extends InfrastructureRequest
{
    public const OPERATION = 'dependencies_get';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES, 'id' => self::ID_RULES, ...$this->paginationRules()];
    }
}
