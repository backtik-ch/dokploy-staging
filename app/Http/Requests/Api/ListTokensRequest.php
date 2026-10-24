<?php

namespace App\Http\Requests\Api;

class ListTokensRequest extends InfrastructureRequest
{
    public const OPERATION = 'tokens_list';

    public const ABILITY = 'tokens:manage';

    public function rules(): array
    {
        return $this->paginationRules();
    }
}
