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

            $key = fn (array $package): string => json_encode([$package['manager'] ?? '', $package['path'] ?? '', $package['name']]);
            $known = $report->dependencies()->get(['manager', 'path', 'name'])->mapWithKeys(fn ($package): array => [$key($package->toArray()) => true]);
            $missing = array_values(array_filter($validated['dependencies'], fn (array $package): bool => ! $known->has($key($package))));
            if ($missing !== []) {
                $report->dependencies()->createMany(array_map(fn (array $package): array => $package + [
                    'lock_observed' => array_key_exists('locked', $package),
                    'installation_observed' => array_key_exists('installed', $package),
                ], $missing));
            }

            return $report;
        });

        return response()->json(['id' => $report->id], $report->wasRecentlyCreated ? 201 : 200);
    }
}
