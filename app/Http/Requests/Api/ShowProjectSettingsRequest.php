<?php

namespace App\Http\Requests\Api;

class ShowProjectSettingsRequest extends InfrastructureRequest
{
    public const OPERATION = 'settings_get';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES];
    }
}
