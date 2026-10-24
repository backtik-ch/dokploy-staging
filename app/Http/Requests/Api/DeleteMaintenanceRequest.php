<?php

namespace App\Http\Requests\Api;

class DeleteMaintenanceRequest extends InfrastructureRequest
{
    public const OPERATION = 'maintenance_delete';

    public const ABILITY = 'maintenance:write';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES];
    }
}
