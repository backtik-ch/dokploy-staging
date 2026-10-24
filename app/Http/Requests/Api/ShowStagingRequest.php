<?php

namespace App\Http\Requests\Api;

class ShowStagingRequest extends InfrastructureRequest
{
    public const OPERATION = 'stagings_get';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
