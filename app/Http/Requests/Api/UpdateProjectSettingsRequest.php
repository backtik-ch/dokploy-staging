<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Arr;

class UpdateProjectSettingsRequest extends UpdateProjectRequest
{
    public const OPERATION = 'settings_update';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return [...Arr::except(parent::rules(), 'id'), 'project_id' => self::ID_RULES];
    }
}
