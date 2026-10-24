<?php

namespace App\Http\Requests\Api;

class StoreConnectionRequest extends InfrastructureRequest
{
    public const OPERATION = 'connections_create';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'token' => ['required', 'string', 'max:65535'],
        ];
    }
}
