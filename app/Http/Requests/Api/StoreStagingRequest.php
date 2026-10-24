<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Arr;

class StoreStagingRequest extends UpdateStagingRequest
{
    public const OPERATION = 'stagings_create';

    public const ABILITY = 'stagings:deploy';

    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), 'id'),
            'project_id' => self::ID_RULES,
            'staging_reference' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:255'],
        ];
    }
}
