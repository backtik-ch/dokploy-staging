<x-filament-panels::page>
    <div @if ($live) wire:poll.10s="poll" @endif>
        {{ $this->filtersForm }}
    </div>

    @if ($this->getLabelDiscoveryError())
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Liste des labels indisponible">
            <p>{{ $this->getLabelDiscoveryError() }} Les logs restent consultables ; actualisez pour recharger les filtres.</p>
        </x-filament::section>
    @endif

    @if ($this->result->error)
        <x-filament::section icon="heroicon-o-signal-slash" heading="Logs indisponibles">
            <p>{{ $this->result->error }}</p>
        </x-filament::section>
    @else
        @if ($showActivity)
            @livewire(\App\Filament\Widgets\Logs\LogVolume::class, ['chart' => $this->result->chart()], key('log-volume'))
        @endif

        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;">
                <x-filament::badge :color="$live && $filters['period'] !== 'custom' ? 'success' : 'gray'" icon="heroicon-o-clock">
                    {{ $this->result->query->start->format('d/m H:i:s') }} → {{ $this->result->query->end->format('d/m H:i:s') }} UTC
                </x-filament::badge>
                @if ($live)
                    <span style="font-size: .75rem; color: var(--gray-500);">Actualisation toutes les 10 s · suspendue pendant la lecture des détails ou la pagination.</span>
                @endif
                @if ($this->result->summary()['limited'])
                    <x-filament::badge color="warning" icon="heroicon-o-funnel">
                        Limite atteinte : {{ number_format($this->result->entries->count(), 0, ',', ' ') }} dernières lignes. Affinez les filtres pour remonter plus loin.
                    </x-filament::badge>
                @endif
        </div>
    @endif

    {{ $this->table }}

    @if ($this->result->query)
        <x-filament::section heading="Requête Loki" icon="heroicon-o-code-bracket" collapsible collapsed compact>
            <code style="display: block; white-space: pre-wrap; overflow-wrap: anywhere; font-size: .75rem;">{{ $this->result->query->expression() }}</code>
            <p style="margin-top: .75rem; font-size: .75rem; color: var(--gray-500);">La recherche et les labels sont appliqués dans Loki avant la limite de lignes. Les widgets, le tri, la pagination et l’export portent sur les lignes chargées.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
