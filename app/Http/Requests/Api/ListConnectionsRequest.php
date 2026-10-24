<?php

namespace App\Http\Requests\Api;

class ListConnectionsRequest extends InfrastructureRequest
{
    public const OPERATION = 'connections_list';

    public const ABILITY = 'infra:read';

    public function rules(): array
    {
        return $this->paginationRules();
    }
}
