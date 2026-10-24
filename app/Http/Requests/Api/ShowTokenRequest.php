<?php

namespace App\Http\Requests\Api;

class ShowTokenRequest extends InfrastructureRequest
{
    public const OPERATION = 'tokens_get';

    public const ABILITY = 'tokens:manage';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
