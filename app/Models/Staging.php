<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staging extends Model
{
    /** @use HasFactory<\Database\Factories\StagingFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];


    protected function casts(): array
    {
        return [
            'environment' => 'encrypted'
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
