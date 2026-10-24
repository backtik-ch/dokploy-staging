<?php

namespace App\Http\Requests\Api;

class ListBranchesRequest extends InfrastructureRequest
{
    public const OPERATION = 'branches_list';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return ['project_id' => self::ID_RULES, 'repository' => ['sometimes', 'string', 'max:255']];
    }
}
