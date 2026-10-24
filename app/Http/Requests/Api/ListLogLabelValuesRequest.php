<?php

namespace App\Http\Requests\Api;

class ListLogLabelValuesRequest extends SearchLogsRequest
{
    public const OPERATION = 'logs_label_values';

    public const ABILITY = 'logs:read';

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'label' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/D'],
        ];
    }
}
