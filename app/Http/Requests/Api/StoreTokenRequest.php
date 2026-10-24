<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Arr;

class StoreTokenRequest extends UpdateTokenRequest
{
    public const OPERATION = 'tokens_create';

    public const ABILITY = 'tokens:manage';

    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), 'id'),
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
