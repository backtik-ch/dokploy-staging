<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends UpdateProjectRequest
{
    public const OPERATION = 'projects_create';

    public const ABILITY = 'infra:write';

    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), 'id'),
            'app_name' => ['required', 'string', 'max:255'],
            'dokploy_id' => ['required', 'integer', Rule::exists('dokploys', 'id')->whereNull('deleted_at')],
        ];
    }
}
