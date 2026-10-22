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

    public function comparisonRows(): array
    {
        return $this->dependencies->map(function (DependencyReportPackage $package): array {
            $dependency = $package->comparisonData();
            $dev = (bool) ($dependency['dev'] ?? false);

            return [
                'name' => $dependency['name'],
                'manager' => $dependency['manager'] ?? null,
                'path' => $dependency['path'] ?? null,
                'required' => $dependency['required'] ?? '—',
                'locked' => $dependency['locked'] ?? '—',
                'installed' => $dependency['installed'] ?? '—',
                'dev' => $dev,
                'direct' => (bool) ($dependency['direct'] ?? true),
                'build_status' => self::comparisonStatus($dependency, $dev),
            ];
        })->sortBy(fn (array $row): string => ($row['dev'] ? '2' : ($row['direct'] ? '0' : '1')).$row['name'])->values()->all();
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
