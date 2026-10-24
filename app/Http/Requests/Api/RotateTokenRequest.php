<?php

namespace App\Http\Requests\Api;

class RotateTokenRequest extends InfrastructureRequest
{
    public const OPERATION = 'tokens_rotate';

    public const ABILITY = 'tokens:manage';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
