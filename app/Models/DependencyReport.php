<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DependencyReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'built_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(DependencyReportPackage::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DependencyAudit::class);
    }

    public function comparisonRows(): array
    {
        return $this->dependencies->map(function (DependencyReportPackage $package): array {
            $dependency = $package->comparisonData();
            $dev = (bool) ($dependency['dev'] ?? false);
            $findings = $package->auditFindings->sortByDesc(fn (DependencyAuditFinding $finding): int => DependencyAuditFinding::severityRank($finding->severity));
            $severity = $findings->first()?->severity;
            $manager = $package->auditManager($this->language_version);
            $audit = $this->audits->firstWhere('manager', $manager);

            return [
                'id' => $package->id,
                'name' => $dependency['name'],
                'manager' => $manager,
                'path' => $dependency['path'] ?? null,
                'required' => $dependency['required'] ?? '—',
                'locked' => $dependency['locked'] ?? '—',
                'installed' => $dependency['installed'] ?? '—',
                'dev' => $dev,
                'direct' => (bool) ($dependency['direct'] ?? true),
                'build_status' => self::comparisonStatus($dependency, $dev),
                'risks' => $findings->map(fn (DependencyAuditFinding $finding): array => [
                    'severity' => DependencyAuditFinding::severityLabel($finding->severity),
                    'color' => DependencyAuditFinding::severityColor($finding->severity),
                    'message' => $finding->message, 'url' => $finding->url,
                ])->values()->all(),
                'risk_count' => $findings->count(),
                'risk_rank' => $severity ? DependencyAuditFinding::severityRank($severity) : 0,
                'risk_label' => $severity ? DependencyAuditFinding::severityLabel($severity)
                    : ($audit?->error ? 'Audit en erreur' : ($package->audited_at ? 'Aucun risque détecté' : ($package->auditVersion() === null ? 'Non concernée' : 'Non auditée'))),
                'risk_color' => $severity ? DependencyAuditFinding::severityColor($severity) : ($audit?->error ? 'warning' : ($package->audited_at ? 'success' : 'gray')),
                'audited_at' => $package->audited_at?->format('d/m/Y H:i'),
                'audit_version' => $package->auditVersion(),
                'audit_source' => $package->installation_observed ? 'Image' : 'Lock',
            ];
        })->sortBy(fn (array $row): string => (6 - $row['risk_rank']).($row['dev'] ? '2' : ($row['direct'] ? '0' : '1')).$row['name'])->values()->all();
    }

    private static function comparisonStatus(array $dependency, bool $dev): string
    {
        if (! array_key_exists('installed', $dependency)) {
            return 'Version installée non relevée';
        }

        if ($dependency['installed'] === null) {
            return $dev ? 'Dev non installée' : 'Non installée';
        }

        if (! array_key_exists('locked', $dependency)) {
            return 'Sans version verrouillée';
        }

        if ($dependency['locked'] === null) {
            return 'Hors lock';
        }

        $equal = ltrim($dependency['locked'], 'v') === ltrim($dependency['installed'], 'v');
        $sameReference = ! isset($dependency['locked_reference'], $dependency['installed_reference'])
            || $dependency['locked_reference'] === $dependency['installed_reference'];

        return $equal && $sameReference ? 'Conforme au lock' : 'Écart avec le lock';
    }
}
