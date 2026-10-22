<?php

namespace App\Services\Loki;

use App\Exceptions\LokiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class LogQueryFactory
{
    public const PERIODS = [
        '15m' => '15 dernières minutes',
        '1h' => 'Dernière heure',
        '6h' => '6 dernières heures',
        '24h' => 'Dernières 24 heures',
        '7d' => '7 derniers jours',
        'custom' => 'Période personnalisée',
    ];

    public function make(array $filters, string $endAt, string $search = '', ?string $exceptLabel = null): LogQuery
    {
        $validator = Validator::make($filters, [
            'period' => 'required|in:'.implode(',', array_keys(self::PERIODS)),
            'project' => 'nullable|string|max:500',
            'service' => 'nullable|string|max:500',
            'environment' => 'nullable|string|max:500',
            'level' => 'nullable|string|max:100',
            'labels' => 'array|max:20',
            'labels.*.name' => 'nullable|string|max:100',
            'labels.*.value' => 'nullable|string|max:1000',
            'limit' => 'required|integer|in:500,1000,2500,5000',
            'case_sensitive' => 'boolean',
            'start' => 'required_if:period,custom|nullable|date',
            'end' => 'required_if:period,custom|nullable|date',
        ]);

        if ($validator->fails()) {
            throw new LokiException('Complétez les filtres avec une période et des valeurs valides.');
        }

        $end = CarbonImmutable::parse($endAt, 'UTC');
        $start = match ($filters['period']) {
            '15m' => $end->subMinutes(15),
            '6h' => $end->subHours(6),
            '24h' => $end->subDay(),
            '7d' => $end->subDays(7),
            'custom' => CarbonImmutable::parse($filters['start'], 'UTC'),
            default => $end->subHour(),
        };

        if ($filters['period'] === 'custom') {
            $end = CarbonImmutable::parse($filters['end'], 'UTC');
        }

        $labels = [];
        foreach ([config('loki.project_label') => 'project', 'service' => 'service', 'env' => 'environment', 'level' => 'level'] as $label => $field) {
            if (filled($filters[$field] ?? null)) {
                $labels[$label] = $filters[$field];
            }
        }
        foreach ($filters['labels'] ?? [] as $filter) {
            if (filled($filter['name'] ?? null) && isset($filter['value']) && $filter['value'] !== '') {
                if (isset($labels[$filter['name']]) && $labels[$filter['name']] !== $filter['value']) {
                    throw new LokiException('Un même label possède deux valeurs différentes. Retirez le filtre contradictoire.');
                }
                $labels[$filter['name']] = $filter['value'];
            }
        }

        if ($exceptLabel !== null) {
            unset($labels[$exceptLabel]);
        }

        return new LogQuery($start, $end, $labels, $search, (bool) ($filters['case_sensitive'] ?? false), (int) $filters['limit']);
    }
}
