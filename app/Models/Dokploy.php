<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dokploy extends Model
{
    /** @use HasFactory<\Database\Factories\DokployFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    public function connectionLabel(): string
    {
        return ($this->name ?: 'Connexion Dokploy').' · '.$this->base_url.' · #'.$this->id;
    }

    protected static function booted(): void
    {
        static::updated(function (Dokploy $dokploy): void {
            if ($dokploy->wasChanged(['base_url', 'token'])) {
                $dokploy->projects()->update([
                    'maintenance_enabled' => null,
                    'maintenance_checked_at' => null,
                    'maintenance_error' => null,
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
