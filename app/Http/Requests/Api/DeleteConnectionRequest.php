<?php

namespace App\Http\Requests\Api;

class DeleteConnectionRequest extends InfrastructureRequest
{
    public const OPERATION = 'connections_delete';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
