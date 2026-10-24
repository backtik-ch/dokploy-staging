<?php

namespace App\Http\Requests\Api;

class RedeployStagingRequest extends InfrastructureRequest
{
    public const OPERATION = 'stagings_redeploy';

    public const ABILITY = 'stagings:deploy';

    public function rules(): array
    {
        return ['id' => self::ID_RULES];
    }
}
