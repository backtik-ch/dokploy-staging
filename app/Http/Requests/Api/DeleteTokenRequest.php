<?php

namespace App\Http\Requests\Api;

class DeleteTokenRequest extends InfrastructureRequest
{
    public const OPERATION = 'tokens_delete';

    public const ABILITY = 'tokens:manage';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
