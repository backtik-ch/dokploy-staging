<?php

namespace App\Http\Requests\Api;

class GetCapabilitiesRequest extends InfrastructureRequest
{
    public const OPERATION = 'capabilities';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return [];
    }
}
