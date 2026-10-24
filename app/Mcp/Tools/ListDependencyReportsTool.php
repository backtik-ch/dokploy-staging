<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListDependencyReportsRequest;
use App\Http\Resources\DependencyReportResource;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\DependencyReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class ListDependencyReportsTool extends InfrastructureTool
{
    protected const REQUEST = ListDependencyReportsRequest::class;

    public function __construct(private readonly DependencyReportService $dependencies) {}

    protected function resource(array $data, User $user): AnonymousResourceCollection
    {
        return DependencyReportResource::collection($this->dependencies->paginate($data['project_id'], Arr::except($data, 'project_id'), $user));
    }
}
