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
            'production_confirmed_at' => 'immutable_datetime',
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

    public function comparisonRows(?self $production = null): array
    {
        $key = fn (array $dependency): string => json_encode([$dependency['manager'] ?? '', $dependency['path'] ?? '', $dependency['name']]);
        $built = $this->dependencies->map(fn (DependencyReportPackage $package): array => $package->comparisonData())->keyBy($key);
        $deployed = collect($production?->dependencies ?? [])->map(fn (DependencyReportPackage $package): array => $package->comparisonData())->keyBy($key);

        return $built->keys()->merge($deployed->keys())->unique()->map(function (string $key) use ($built, $deployed, $production): array {
            $build = $built->get($key);
            $prod = $deployed->get($key);
            $dependency = $build ?? $prod;
            $dev = (bool) ($dependency['dev'] ?? false);

            return [
                'name' => $dependency['name'],
                'manager' => $dependency['manager'] ?? null,
                'path' => $dependency['path'] ?? null,
                'required' => $build['required'] ?? '—',
                'locked' => $build['locked'] ?? '—',
                'installed' => $build['installed'] ?? '—',
                'production' => $prod['installed'] ?? '—',
                'dev' => $dev,
                'direct' => (bool) ($dependency['direct'] ?? true),
                'build_status' => self::comparisonStatus($build, $build, $dev),
                'production_status' => $production === null ? 'Non confirmée' : self::comparisonStatus($build, $prod, $dev),
            ];
        })->sortBy(fn (array $row): string => ($row['dev'] ? '2' : ($row['direct'] ? '0' : '1')).$row['name'])->values()->all();
    }

    private static function comparisonStatus(?array $lock, ?array $observed, bool $dev): string
    {
        if ($observed === null) {
            return 'Absente du relevé';
        }

        if (! array_key_exists('installed', $observed)) {
            return 'Version installée non relevée';
        }

        if ($observed['installed'] === null) {
            return $dev ? 'Dev non installée' : 'Non installée';
        }

        if ($lock === null || ! array_key_exists('locked', $lock)) {
            return 'Sans version verrouillée';
        }

        if ($lock['locked'] === null) {
            return 'Hors lock';
        }

        $equal = ltrim($lock['locked'], 'v') === ltrim($observed['installed'], 'v');
        $sameReference = ! isset($lock['locked_reference'], $observed['installed_reference'])
            || $lock['locked_reference'] === $observed['installed_reference'];

        return $equal && $sameReference ? 'Conforme au lock' : 'Écart avec le lock';
    }
}
