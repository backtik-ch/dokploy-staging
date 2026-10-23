<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DependencyReportPackage extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lock_observed' => 'boolean',
            'installation_observed' => 'boolean',
            'dev' => 'boolean',
            'direct' => 'boolean',
            'audited_at' => 'immutable_datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DependencyReport::class, 'dependency_report_id');
    }

    public function comparisonData(): array
    {
        $data = $this->only(['name', 'manager', 'path', 'required', 'locked_reference', 'installed_reference', 'dev', 'direct']);
        if ($this->lock_observed) {
            $data['locked'] = $this->locked;
        }
        if ($this->installation_observed) {
            $data['installed'] = $this->installed;
        }

        return $data;
    }

    public function auditFindings(): HasMany
    {
        return $this->hasMany(DependencyAuditFinding::class);
    }

    public function auditManager(string $languageVersion): ?string
    {
        return $this->manager ?: match (true) {
            str_starts_with(strtolower($languageVersion), 'php') => 'composer',
            preg_match('/^node(?:\.js|js|\s|$)/i', $languageVersion) === 1 => 'npm',
            default => null,
        };
    }

    public function auditVersion(): ?string
    {
        return $this->installation_observed ? $this->installed : ($this->lock_observed ? $this->locked : null);
    }
}
