<?php

namespace App\Http\Requests\Api;

class ResetProjectSettingsRequest extends InfrastructureRequest
{
    public const OPERATION = 'settings_reset';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES];
    }
}
