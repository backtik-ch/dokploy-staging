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

    protected function casts(): array
    {
        return [
            'token' => 'encrypted'
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
