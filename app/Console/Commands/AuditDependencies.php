<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\DependencyAudit\DependencyAuditNotifier;
use App\Services\DependencyAudit\DependencyAuditor;
use App\Services\DependencyAudit\DependencyAuditRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditDependencies extends Command
{
    protected $signature = 'dependencies:audit {--project= : Limit the audit to a staging project ID}';

    protected $description = 'Audit the latest main image dependencies and notify when issues are found';

    public function handle(DependencyAuditor $auditor, DependencyAuditNotifier $notifier, DependencyAuditRecorder $recorder): int
    {
        if ($this->option('project') !== null && (! ctype_digit($this->option('project')) || (int) $this->option('project') < 1)) {
            $this->error('The project ID must be a positive integer.');

            return self::INVALID;
        }

        $failed = false;
        Project::query()->whereHas('dependencyReports', fn ($query) => $query->where('branch', 'main'))
            ->when($this->option('project'), fn ($query, $id) => $query->whereKey($id))
            ->chunkById(50, function ($projects) use ($auditor, $notifier, $recorder, &$failed): void {
                foreach ($projects as $project) {
                    foreach ($project->latestDependencyReportsByImage() as $report) {
                        $report->setRelation('project', $project);
                        foreach ($auditor->inventories($report) as $manager => $packages) {
                            try {
                                $findings = $auditor->auditPackages($manager, $packages);
                                $recorder->success($report, $manager, $packages, $findings);
                            } catch (Throwable $exception) {
                                $failed = true;
                                $recorder->failure($report, $manager, $exception->getMessage());
                                Log::error('Dependency audit failed', [
                                    'project_id' => $project->id, 'report_id' => $report->id,
                                    'manager' => $manager, 'error' => $exception->getMessage(),
                                ]);
                                $this->error('Audit '.$manager.' impossible pour '.$project->app_name.': '.$exception->getMessage());

                                continue;
                            }

                            try {
                                $notifier->send($report, $findings);
                            } catch (Throwable $exception) {
                                $failed = true;
                                Log::error('Dependency audit notification failed', [
                                    'project_id' => $project->id,
                                    'project_name' => $project->app_name,
                                    'image' => $report->image,
                                    'report_id' => $report->id,
                                    'error' => $exception->getMessage(),
                                    'manager' => $manager,
                                ]);
                                $this->error('Notification impossible pour '.$project->app_name.': '.$exception->getMessage());
                            }
                        }
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
