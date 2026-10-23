<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DependencyAudit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime', 'audited_at' => 'immutable_datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DependencyReport::class, 'dependency_report_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(DependencyAuditFinding::class);
    }
}
