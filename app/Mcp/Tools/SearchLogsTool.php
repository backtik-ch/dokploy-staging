<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\SearchLogsRequest;
use App\Http\Resources\LogCollection;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\LogService;

class SearchLogsTool extends InfrastructureTool
{
    protected const REQUEST = SearchLogsRequest::class;

    public function __construct(private readonly LogService $logs) {}

    protected function resource(array $data, User $user): LogCollection
    {
        return new LogCollection($this->logs->search($data, $user));
    }
}
