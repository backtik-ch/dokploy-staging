<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLogLabelsRequest;
use App\Http\Requests\Api\ListLogLabelValuesRequest;
use App\Http\Requests\Api\SearchLogsRequest;
use App\Http\Resources\LogCollection;
use App\Http\Resources\LogLabelCollection;
use App\Services\Infrastructure\LogService;

class LogController extends Controller
{
    public function __construct(private readonly LogService $logs) {}

    public function index(SearchLogsRequest $request): LogCollection
    {
        $logs = $this->logs->search($request->validated(), $request->user());

        return new LogCollection($logs);
    }

    public function labels(ListLogLabelsRequest $request): LogLabelCollection
    {
        $logs = $this->logs->labels($request->validated(), $request->user());

        return new LogLabelCollection($logs);
    }

    public function labelValues(ListLogLabelValuesRequest $request): LogLabelCollection
    {
        $logs = $this->logs->labelValues(
            $request->validated('label'),
            $request->safe()->except('label'),
            $request->user(),
        );

        return new LogLabelCollection($logs);
    }
}
