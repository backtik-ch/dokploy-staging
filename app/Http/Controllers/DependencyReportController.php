<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDependencyReportRequest;
use App\Models\DependencyReport;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DependencyReportController extends Controller
{
    public function store(StoreDependencyReportRequest $request): JsonResponse
    {
        $project = $request->project();
        $validated = $request->validated();

        $report = DB::transaction(function () use ($project, $validated): DependencyReport {
            // Serialize reports for this project so a retry cannot duplicate packages.
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $attributes = $validated;
            unset($attributes['dependencies']);
            $report = DependencyReport::firstOrCreate([
                'project_id' => $project->id,
                'image' => $validated['image'],
                'digest' => $validated['digest'],
                'platform' => $validated['platform'],
            ], $attributes);

            if ($report->wasRecentlyCreated) {
                $report->dependencies()->createMany(array_map(fn (array $package): array => $package + [
                    'lock_observed' => array_key_exists('locked', $package),
                    'installation_observed' => array_key_exists('installed', $package),
                ], $validated['dependencies']));
            }

            return $report;
        });

        return response()->json(['id' => $report->id], $report->wasRecentlyCreated ? 201 : 200);
    }
}
