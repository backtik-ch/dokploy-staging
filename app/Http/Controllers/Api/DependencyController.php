<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListDependencyReportsRequest;
use App\Http\Requests\Api\ShowDependencyReportRequest;
use App\Http\Resources\DependencyReportDetailsResource;
use App\Http\Resources\DependencyReportResource;
use App\Services\Infrastructure\DependencyReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DependencyController extends Controller
{
    public function __construct(private readonly DependencyReportService $dependencies) {}

    public function index(ListDependencyReportsRequest $request): AnonymousResourceCollection
    {
        $reports = $this->dependencies->paginate(
            $request->validated('project_id'),
            $request->safe()->except('project_id'),
            $request->user(),
        );

        return DependencyReportResource::collection($reports);
    }

    public function show(ShowDependencyReportRequest $request): DependencyReportDetailsResource
    {
        $report = $this->dependencies->find(
            $request->validated('project_id'),
            $request->validated('id'),
            $request->safe()->except(['project_id', 'id']),
            $request->user(),
        );

        return new DependencyReportDetailsResource($report);
    }
}
