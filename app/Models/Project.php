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

    protected function casts(): array
    {
        return [
            'environment_staging' => 'encrypted',
            'extra_sub_domains' => 'array',
        ];
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
