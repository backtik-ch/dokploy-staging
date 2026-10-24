<?php

namespace App\Http\Requests\Api;

class UpdateConnectionRequest extends InfrastructureRequest
{
    public const OPERATION = 'connections_update';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return [
            'id' => self::ID_RULES,
            'name' => ['sometimes', 'string', 'max:255'],
            'base_url' => ['sometimes', 'url:http,https', 'max:255'],
            'token' => ['sometimes', 'string', 'max:65535'],
        ];
    }
}
