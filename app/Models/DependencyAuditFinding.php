<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DependencyAuditFinding extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(DependencyAudit::class, 'dependency_audit_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DependencyReportPackage::class, 'dependency_report_package_id');
    }

    public static function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 6, 'high' => 5, 'medium', 'moderate' => 4,
            'low' => 3, 'unknown' => 2, 'info' => 1, default => 0,
        };
    }

    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critique', 'high' => 'Élevée', 'medium', 'moderate' => 'Moyenne',
            'low' => 'Faible', 'info' => 'Information', default => 'Non précisée',
        };
    }

    public static function severityColor(string $severity): string
    {
        return match ($severity) {
            'critical', 'high' => 'danger', 'medium', 'moderate', 'low', 'unknown' => 'warning',
            default => 'info',
        };
    }
}
