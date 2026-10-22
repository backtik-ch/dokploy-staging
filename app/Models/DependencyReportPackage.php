<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
