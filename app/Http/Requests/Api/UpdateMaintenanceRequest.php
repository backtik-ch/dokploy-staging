<?php

namespace App\Http\Requests\Api;

use App\Rules\MaintenanceFile;

class UpdateMaintenanceRequest extends InfrastructureRequest
{
    public const OPERATION = 'maintenance_set';

    public const ABILITY = 'maintenance:write';

    public function rules(): array
    {
        return [
            'project_id' => self::ID_RULES,
            'maintenance_yml_file' => ['required_without:enabled', 'string', 'max:255', new MaintenanceFile],
            'enabled' => ['required_without:maintenance_yml_file', 'boolean'],
        ];
    }
}
