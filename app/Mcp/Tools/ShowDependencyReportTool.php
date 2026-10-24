<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ShowDependencyReportRequest;
use App\Http\Resources\DependencyReportDetailsResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\DependencyReportService;
use Illuminate\Support\Arr;

class ShowDependencyReportTool extends InfrastructureTool
{
    protected const REQUEST = ShowDependencyReportRequest::class;

    public function __construct(private readonly DependencyReportService $dependencies) {}

    protected function resource(array $data, User $user): DependencyReportDetailsResource
    {
        return new DependencyReportDetailsResource($this->dependencies->find($data['project_id'], $data['id'], Arr::except($data, ['project_id', 'id']), $user));
    }
}
