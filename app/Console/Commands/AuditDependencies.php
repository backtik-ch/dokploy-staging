<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\DependencyAudit\DependencyAuditNotifier;
use App\Services\DependencyAudit\DependencyAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditDependencies extends Command
{
    protected $signature = 'dependencies:audit {--project= : Limit the audit to a staging project ID}';

    protected $description = 'Audit the latest main image dependencies and notify when issues are found';

    public function handle(DependencyAuditor $auditor, DependencyAuditNotifier $notifier): int
    {
        if ($this->option('project') !== null && (! ctype_digit($this->option('project')) || (int) $this->option('project') < 1)) {
            $this->error('The project ID must be a positive integer.');

            return self::INVALID;
        }

        $failed = false;
        Project::query()->whereHas('dependencyReports', fn ($query) => $query->where('branch', 'main'))
            ->when($this->option('project'), fn ($query, $id) => $query->whereKey($id))
            ->chunkById(50, function ($projects) use ($auditor, $notifier, &$failed): void {
                foreach ($projects as $project) {
                    foreach ($project->latestDependencyReportsByImage() as $report) {
                        try {
                            $report->setRelation('project', $project);
                            $notifier->send($report, $auditor->audit($report));
                        } catch (Throwable $exception) {
                            $failed = true;
                            Log::error('Dependency audit failed', [
                                'project_id' => $project->id,
                                'project_name' => $project->app_name,
                                'image' => $report->image,
                                'report_id' => $report->id,
                                'error' => $exception->getMessage(),
                            ]);
                            $this->error('Audit impossible pour '.$project->app_name.' ('.$report->image.'): '.$exception->getMessage());
                        }
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
