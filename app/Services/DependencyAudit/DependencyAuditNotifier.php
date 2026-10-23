<?php

namespace App\Services\DependencyAudit;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\DependencyReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DependencyAuditNotifier
{
    public function send(DependencyReport $report, array $findings): void
    {
        foreach ($findings as $finding) {
            // A rebuild, another platform or a new hourly audit must not repeat a known issue.
            $key = 'dependency-audit:daily:'.hash('sha256', json_encode([
                $report->project_id, $report->repository, $finding['manager'],
                $finding['dependency'], $finding['severity'], $finding['message'], $finding['url'] ?? null,
            ], JSON_THROW_ON_ERROR));

            Cache::lock($key.':lock', 120)->block(5, function () use ($report, $finding, $key): void {
                if (Cache::has($key)) {
                    return;
                }

                $token = config('dependency-audit.token');
                if (blank($token)) {
                    throw new RuntimeException('DEPENDENCY_AUDIT_WEBHOOK_TOKEN is required to send dependency audit alerts.');
                }

                Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(60)
                    ->post(rtrim(config('dependency-audit.notifications_url'), '/').'/api/notifications/dependency-audits', [
                        'project_id' => $report->project_id,
                        'project_name' => $report->project->app_name,
                        'repository' => $report->repository,
                        'image' => $report->image,
                        'digest' => $report->digest,
                        'platform' => $report->platform,
                        'report_url' => ProjectResource::getUrl('dependencies', ['record' => $report->project_id], panel: 'admin'),
                        'audited_at' => now()->toIso8601String(),
                        'findings' => [$finding],
                    ])->throw();

                Cache::put($key, true, 86400);
            });
        }
    }
}
