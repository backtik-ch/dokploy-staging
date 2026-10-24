<?php

namespace App\Http\Requests\Api;

class ShowMaintenanceRequest extends InfrastructureRequest
{
    public const OPERATION = 'maintenance_get';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES];
    }
}
