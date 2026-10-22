<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmDependencyDeploymentRequest;
use App\Http\Requests\StoreDependencyReportRequest;
use App\Models\DependencyReport;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function deployed(ConfirmDependencyDeploymentRequest $request): JsonResponse
    {
        $project = $request->project();
        $validated = $request->safe()->except('project_id');

        $report = DB::transaction(function () use ($project, $validated): DependencyReport {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $report = $project->dependencyReports()->where($validated)->first();

            if (! $report) {
                throw ValidationException::withMessages(['digest' => 'Aucun rapport connu pour cette image, ce digest et cette plateforme.']);
            }

            $project->dependencyReports()->where('image', $validated['image'])->where('platform', $validated['platform'])
                ->whereNotNull('production_confirmed_at')->update(['production_confirmed_at' => null]);
            $report->update(['production_confirmed_at' => now()]);

            return $report;
        });

        return response()->json(['id' => $report->id, 'production_confirmed_at' => $report->production_confirmed_at]);
    }
}
