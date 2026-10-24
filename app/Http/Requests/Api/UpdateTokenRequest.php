<?php

namespace App\Http\Requests\Api;

use App\Services\Infrastructure\ApiAccess;
use Illuminate\Validation\Rule;

class UpdateTokenRequest extends InfrastructureRequest
{
    public const OPERATION = 'tokens_update';

    public const ABILITY = 'tokens:manage';

    public function rules(): array
    {
        return [
            'id' => self::ID_RULES,
            'name' => ['sometimes', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'list', 'min:1', 'max:20'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in(['*', ...ApiAccess::ABILITIES])],
            'expires_at' => ['sometimes', 'date', 'after:now'],
        ];
    }
}
