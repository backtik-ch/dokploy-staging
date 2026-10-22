<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
