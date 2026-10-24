<?php

namespace App\Http\Requests\Api;

class ShowConnectionRequest extends InfrastructureRequest
{
    public const OPERATION = 'connections_get';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
