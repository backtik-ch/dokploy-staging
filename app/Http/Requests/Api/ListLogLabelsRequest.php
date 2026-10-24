<?php

namespace App\Http\Requests\Api;

class ListLogLabelsRequest extends SearchLogsRequest
{
    public const OPERATION = 'logs_labels';

    public const ABILITY = 'logs:read';
}
