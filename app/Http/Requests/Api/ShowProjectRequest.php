<?php

namespace App\Http\Requests\Api;

class ShowProjectRequest extends InfrastructureRequest
{
    public const OPERATION = 'projects_get';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
