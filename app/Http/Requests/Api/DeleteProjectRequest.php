<?php

namespace App\Http\Requests\Api;

class DeleteProjectRequest extends InfrastructureRequest
{
    public const OPERATION = 'projects_delete';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
