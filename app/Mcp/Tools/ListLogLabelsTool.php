<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListLogLabelsRequest;
use App\Http\Resources\LogLabelCollection;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\LogService;

class ListLogLabelsTool extends InfrastructureTool
{
    protected const REQUEST = ListLogLabelsRequest::class;

    public function __construct(private readonly LogService $logs) {}

    protected function resource(array $data, User $user): LogLabelCollection
    {
        return new LogLabelCollection($this->logs->labels($data, $user));
    }
}
