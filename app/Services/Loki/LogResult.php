<?php

namespace App\Services\Loki;

use Illuminate\Support\Collection;

final readonly class LogResult
{
    public function __construct(
        public Collection $entries,
        public ?LogQuery $query = null,
        public ?string $error = null,
    ) {}

    public function summary(): array
    {
        $errors = $this->entries->whereIn('level', ['error', 'critical'])->count();
        $warnings = $this->entries->where('level', 'warning')->count();
        $buckets = array_fill(0, 24, 0);
        $errorBuckets = $buckets;

        if ($this->query) {
            $start = $this->query->start->getTimestamp();
            $duration = max(1, $this->query->end->getTimestamp() - $start);

            foreach ($this->entries as $entry) {
                $bucket = max(0, min(23, (int) floor((((int) substr($entry['timestamp'], 0, -9)) - $start) / $duration * 24)));
                $buckets[$bucket]++;
                if (in_array($entry['level'], ['error', 'critical'])) {
                    $errorBuckets[$bucket]++;
                }
            }
        }

        return [
            'count' => $this->entries->count(),
            'errors' => $errors,
            'warnings' => $warnings,
            'streams' => $this->entries->pluck('stream')->unique()->count(),
            'buckets' => $buckets,
            'errorBuckets' => $errorBuckets,
            'limited' => $this->query && $this->entries->count() >= $this->query->parameters()['limit'],
        ];
    }

    public function chart(): array
    {
        $summary = $this->summary();
        $labels = [];
        $duration = $this->query ? $this->query->end->getTimestamp() - $this->query->start->getTimestamp() : 0;

        for ($i = 0; $i < 24; $i++) {
            $labels[] = $this->query?->start->addSeconds((int) ($duration * $i / 24))->format($duration > 86400 ? 'd/m H:i' : 'H:i').' UTC';
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Autres niveaux', 'data' => array_map(fn (int $all, int $errors): int => $all - $errors, $summary['buckets'], $summary['errorBuckets']), 'backgroundColor' => '#38bdf8', 'borderRadius' => 3],
                ['label' => 'Erreurs', 'data' => $summary['errorBuckets'], 'backgroundColor' => '#f43f5e', 'borderRadius' => 3],
            ],
        ];
    }
}
