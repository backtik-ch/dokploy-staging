<?php

namespace App\Http\Requests\Api;

class DeleteStagingRequest extends InfrastructureRequest
{
    public const OPERATION = 'stagings_delete';

    public const ABILITY = 'stagings:delete';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
