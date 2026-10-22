<?php

namespace App\Filament\Widgets\Logs;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class LogVolume extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Activité des logs';

    protected ?string $description = 'Répartition des lignes chargées sur la période sélectionnée. Les résultats peuvent être limités.';

    protected ?string $maxHeight = '160px';

    protected bool $isCollapsible = true;

    #[Reactive]
    public array $chart = [];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return $this->chart;
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
