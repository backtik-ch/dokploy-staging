<?php

namespace App\Filament\Widgets\Logs;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class LogStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    #[Reactive]
    public array $summary = [];

    #[Reactive]
    public bool $unavailable = false;

    protected function getStats(): array
    {
        $value = fn (string $key): string => $this->unavailable ? '—' : number_format($this->summary[$key] ?? 0, 0, ',', ' ');

        return [
            Stat::make('Logs chargés', $value('count'))
                ->description(($this->summary['limited'] ?? false) ? 'Limite atteinte' : 'Dans la sélection')
                ->descriptionIcon('heroicon-o-command-line')
                ->chart($this->summary['buckets'] ?? [0])->color('primary'),
            Stat::make('Erreurs', $value('errors'))->description('Error · critical')
                ->descriptionIcon('heroicon-o-exclamation-circle')
                ->chart($this->summary['errorBuckets'] ?? [0])->color('danger'),
            Stat::make('Avertissements', $value('warnings'))->description('Niveau warning')
                ->descriptionIcon('heroicon-o-exclamation-triangle')->color('warning'),
            Stat::make('Flux distincts', $value('streams'))->description('Labels uniques')
                ->descriptionIcon('heroicon-o-squares-2x2')->color('info'),
        ];
    }
}
