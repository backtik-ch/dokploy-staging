<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class SearchLogsRequest extends InfrastructureRequest
{
    public const OPERATION = 'logs_search';

    public const ABILITY = 'logs:read';

    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::in(['15m', '1h', '6h', '24h', '7d', 'custom'])],
            'start' => ['required_if:period,custom', 'date'],
            'end' => ['required_if:period,custom', 'date', 'after:start'],
            'project' => ['sometimes', 'string', 'max:500'],
            'service' => ['sometimes', 'string', 'max:255'],
            'environment' => ['sometimes', 'string', 'max:255'],
            'level' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:500'],
            'case_sensitive' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', Rule::in([500, 1000, 2500, 5000])],
            'labels' => ['sometimes', 'array', 'list', 'max:20'],
            'labels.*' => ['array:name,value'],
            'labels.*.name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/D'],
            'labels.*.value' => ['required', 'string', 'max:1000'],
        ];
    }
}
