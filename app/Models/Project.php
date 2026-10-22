<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Project $project): void {
            if ($project->isDirty(['maintenance_yml_file', 'dokploy_id', 'server_id'])) {
                $project->maintenance_enabled = null;
                $project->maintenance_checked_at = null;
                $project->maintenance_error = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'environment_staging' => 'encrypted',
            'github_token' => 'encrypted',
            'extra_sub_domains' => 'array',
            'linked_repositories' => 'array',
            'wait_for_main_image' => 'boolean',
            'service_port' => 'integer',
            'maintenance_enabled' => 'boolean',
            'maintenance_checked_at' => 'datetime',
        ];
    }

    public function hasStagingConfiguration(): bool
    {
        foreach (['dokploy_project_id', 'github_id', 'github_owner', 'github_repository', 'compose_name_file', 'domain_name'] as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }

    public function maintenanceStatusLabel(): string
    {
        if (blank($this->maintenance_yml_file)) {
            return 'Non configurée';
        }

        if (filled($this->maintenance_error)) {
            return 'À vérifier';
        }

        return match ($this->maintenance_enabled) {
            true => 'En maintenance',
            false => 'En ligne',
            null => 'Non vérifiée',
        };
    }

    public function maintenanceStatusColor(): string
    {
        return match (true) {
            filled($this->maintenance_error) => 'danger',
            $this->maintenance_enabled === true => 'warning',
            $this->maintenance_enabled === false => 'success',
            default => 'gray',
        };
    }

    public function dokploy(): BelongsTo
    {
        return $this->belongsTo(Dokploy::class);
    }

    public function stagings(): HasMany
    {
        return $this->hasMany(Staging::class);
    }

    public function dependencyReports(): HasMany
    {
        return $this->hasMany(DependencyReport::class);
    }

    public function latestDependencyReport(): HasOne
    {
        return $this->hasOne(DependencyReport::class)->ofMany(['built_at' => 'max', 'id' => 'max']);
    }

    public function dependencyComparisons(): \Illuminate\Support\Collection
    {
        // Only load the most recent build and the confirmed production for each image/platform.
        $latest = $this->dependencyReports()->select('id')->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('dependency_reports as newer')
                ->whereColumn('newer.project_id', 'dependency_reports.project_id')
                ->whereColumn('newer.image', 'dependency_reports.image')
                ->whereColumn('newer.platform', 'dependency_reports.platform')
                ->where(function ($query): void {
                    $query->whereColumn('newer.built_at', '>', 'dependency_reports.built_at')
                        ->orWhere(function ($query): void {
                            $query->whereColumn('newer.built_at', 'dependency_reports.built_at')->whereColumn('newer.id', '>', 'dependency_reports.id');
                        });
                });
        });

        return $this->dependencyReports()->with('dependencies')->where(fn ($query) => $query->whereIn('id', $latest)->orWhereNotNull('production_confirmed_at'))
            ->orderByDesc('built_at')->orderByDesc('id')->get()
            ->groupBy(fn (DependencyReport $report): string => $report->image.'@'.$report->platform)
            ->map(fn ($reports): array => ['build' => $reports->first(), 'production' => $reports->firstWhere('production_confirmed_at', '!=', null)]);
    }
}
