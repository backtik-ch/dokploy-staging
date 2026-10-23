<?php

namespace App\Services\DependencyAudit;

use App\Models\DependencyReport;
use App\Models\DependencyReportPackage;
use Illuminate\Support\Facades\DB;

class DependencyAuditRecorder
{
    public function success(DependencyReport $report, string $manager, array $packages, array $findings): void
    {
        DB::transaction(function () use ($report, $manager, $packages, $findings): void {
            DependencyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $audit = $report->audits()->updateOrCreate(['manager' => $manager], [
                'attempted_at' => now(), 'audited_at' => now(), 'error' => null,
            ]);
            $audit->findings()->delete();

            foreach ($packages as $package) {
                foreach ($findings as $finding) {
                    if ($package->name === $finding['dependency'] && in_array($package->auditVersion(), $finding['versions'], true)) {
                        $audit->findings()->create([
                            'dependency_report_package_id' => $package->id,
                            'severity' => $finding['severity'], 'message' => $finding['message'], 'url' => $finding['url'] ?? null,
                        ]);
                    }
                }
            }
            DependencyReportPackage::whereIn('id', array_map(fn (DependencyReportPackage $package): int => $package->id, $packages))
                ->update(['audited_at' => $audit->audited_at]);
        });
    }

    public function failure(DependencyReport $report, string $manager, string $message): void
    {
        $report->audits()->updateOrCreate(['manager' => $manager], ['attempted_at' => now(), 'error' => $message]);
    }
}
