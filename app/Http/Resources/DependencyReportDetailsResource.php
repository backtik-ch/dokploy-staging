<?php

namespace App\Http\Resources;

use App\Models\DependencyReport;
use App\Services\Infrastructure\DependencyReportDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DependencyReportDetailsResource extends ResourceCollection
{
    public $collects = DependencyPackageResource::class;

    private readonly DependencyReport $report;

    public function __construct(DependencyReportDetails $details)
    {
        $this->report = $details->report;
        parent::__construct($details->dependencies);
    }

    public function toArray(Request $request): array
    {
        return [
            ...DependencyReportResource::make($this->report)->resolve($request),
            'dependencies' => parent::toArray($request),
        ];
    }
}
