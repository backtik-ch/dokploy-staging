<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListLogLabelValuesRequest;
use App\Http\Resources\LogLabelCollection;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\LogService;
use Illuminate\Support\Arr;

class ListLogLabelValuesTool extends InfrastructureTool
{
    protected const REQUEST = ListLogLabelValuesRequest::class;

    public function __construct(private readonly LogService $logs) {}

    protected function resource(array $data, User $user): LogLabelCollection
    {
        return new LogLabelCollection($this->logs->labelValues($data['label'], Arr::except($data, 'label'), $user));
    }
}
