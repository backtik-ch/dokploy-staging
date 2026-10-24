<?php

namespace App\Http\Requests\Api;

class UpdateStagingRequest extends InfrastructureRequest
{
    public const OPERATION = 'stagings_update';

    public const ABILITY = 'stagings:deploy';

    public function rules(): array
    {
        return [
            'id' => self::ID_RULES,
            'branch' => ['sometimes', 'string', 'max:255'],
            'selected_branches' => ['sometimes', 'array', 'max:50'],
            'selected_branches.*' => ['string', 'max:255'],
        ];
    }
}
