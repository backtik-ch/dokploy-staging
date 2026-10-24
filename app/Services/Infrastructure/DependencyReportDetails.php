<?php

namespace App\Services\Infrastructure;

use App\Models\DependencyReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class DependencyReportDetails
{
    public function __construct(
        public DependencyReport $report,
        public LengthAwarePaginator $dependencies,
    ) {}
}
