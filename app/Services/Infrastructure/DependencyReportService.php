<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DependencyReportService
{
    public function __construct(private readonly ProjectService $projects) {}

    public function paginate(int $projectId, array $filters, User $user): LengthAwarePaginator
    {
        ApiAccess::authorize($user, 'infra:read');
        $project = $this->projects->find($projectId, $user);

        return $project->dependencyReports()->latest('built_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
    }

    public function find(int $projectId, int $id, array $filters, User $user): DependencyReportDetails
    {
        ApiAccess::authorize($user, 'infra:read');
        $project = $this->projects->find($projectId, $user);
        $report = $project->dependencyReports()->with('audits')->findOrFail($id);
        $dependencies = $report->dependencies()->with('auditFindings')->orderBy('id')
            ->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();

        return new DependencyReportDetails($report, $dependencies);
    }
}
