<x-filament-panels::page>
    <div class="project-dependencies">
        @if ($comparison = $this->comparison)
            @php
                $build = $comparison['build'];
                $production = $comparison['production'];
                $differences = $this->rows->where('dev', false)->filter(fn ($row) => \App\Filament\Resources\Projects\Pages\ViewProjectDependencies::statusColor($row['build_status']) === 'warning')->count();
            @endphp

            <div class="pd-heading">
                <div>
                    <h2>Dépendances</h2>
                    <p>Comparez les versions du lock, de l’image construite et de la production.</p>
                </div>
                @if ($this->comparisons->count() > 1)
                    <div class="pd-image-select">
                        <label for="dependency-image">Image et plateforme</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="dependency-image" wire:model.live="image">
                                @foreach ($this->comparisons as $key => $item)
                                    <option value="{{ $key }}">{{ $item['build']->image }} · {{ $item['build']->platform }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @else
                    <x-filament::badge color="gray" icon="heroicon-o-cube">{{ $build->platform }}</x-filament::badge>
                @endif
            </div>

            <div class="pd-summaries">
                <x-filament::section icon="heroicon-o-cube" heading="Dernière image construite" compact>
                    <div class="pd-summary">
                        <p class="pd-version">{{ $build->language_version }}</p>
                        <div class="pd-summary-meta">
                            <x-filament::badge color="primary">{{ $build->branch }}</x-filament::badge>
                            <span title="{{ $build->built_at->format('d/m/Y H:i:s T') }}">{{ $build->built_at->diffForHumans() }}</span>
                        </div>
                        <p class="pd-muted">{{ $build->built_at->format('d/m/Y à H:i') }} · <code>{{ substr($build->commit_sha, 0, 8) }}</code></p>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-server-stack" heading="Production" compact>
                    <div class="pd-summary">
                        @if ($production)
                            <p class="pd-version">{{ $production->language_version }}</p>
                            <div class="pd-summary-meta">
                                <x-filament::badge :color="$production->digest === $build->digest ? 'success' : 'gray'">
                                    {{ $production->digest === $build->digest ? 'Dernier build déployé' : 'Build précédent' }}
                                </x-filament::badge>
                            </div>
                            <p class="pd-muted">Confirmée le {{ $production->production_confirmed_at->format('d/m/Y à H:i') }} · <code>{{ substr($production->commit_sha, 0, 8) }}</code></p>
                        @else
                            <p class="pd-version pd-unconfirmed">Non confirmée</p>
                            <p class="pd-muted">Les versions apparaîtront après confirmation de l’image déployée.</p>
                        @endif
                    </div>
                </x-filament::section>
            </div>

            <div class="pd-inventory">
                <p><strong>{{ $this->rows->count() }}</strong> dépendances répertoriées</p>
                <x-filament::badge :color="$differences ? 'warning' : 'success'" :icon="$differences ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'">
                    {{ $differences ? $differences.' écart(s) image / lock' : 'Aucun écart image / lock détecté' }}
                </x-filament::badge>
            </div>

            <x-filament::tabs label="Catégories de dépendances">
                @foreach ($this->scopes as $key => $category)
                    <x-filament::tabs.item
                        :active="$scope === $key"
                        :badge="$category['count']"
                        wire:click="$set('scope', '{{ $key }}')"
                    >
                        {{ $category['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <div wire:key="dependency-table-{{ $build->id }}">
                {{ $this->table }}
            </div>
            <p class="pd-muted pd-help">Les couleurs comparent chaque version au lock du dernier build. Survolez une version pour afficher son statut.</p>

            <x-filament::section heading="Détails de l’image" icon="heroicon-o-finger-print" collapsible collapsed compact>
                <dl class="pd-details">
                    <div><dt>Image</dt><dd>{{ $build->image }}</dd></div>
                    <div><dt>Plateforme</dt><dd>{{ $build->platform }}</dd></div>
                    <div><dt>Dépôt</dt><dd>{{ $build->repository }}</dd></div>
                    <div><dt>Commit du build</dt><dd><code>{{ $build->commit_sha }}</code></dd></div>
                    <div><dt>Digest du build</dt><dd><code>{{ $build->digest }}</code></dd></div>
                    @if ($production)
                        <div><dt>Digest en production</dt><dd><code>{{ $production->digest }}</code></dd></div>
                    @endif
                </dl>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="pd-empty">
                    <x-filament::icon icon="heroicon-o-cube-transparent" class="pd-empty-icon" />
                    <h2>Les versions de votre projet, au même endroit</h2>
                    <p>Le premier rapport affichera le langage et les dépendances de l’image construite, avec une comparaison du lock et de la production.</p>
                    <x-filament::badge color="gray">En attente du premier build sur main</x-filament::badge>
                </div>
            </x-filament::section>
        @endif
    </div>

    @once
        <style>
            .project-dependencies { display: grid; gap: 1.5rem; }
            .project-dependencies .pd-heading { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; }
            .project-dependencies h2 { font-size: 1.125rem; font-weight: 600; }
            .project-dependencies .pd-heading p, .project-dependencies .pd-muted, .project-dependencies dt, .project-dependencies .pd-empty p { color: var(--gray-500); font-size: .875rem; line-height: 1.6; }
            .project-dependencies .pd-heading p { margin-top: .25rem; }
            .project-dependencies .pd-image-select { min-width: 15rem; max-width: 28rem; }
            .project-dependencies .pd-image-select label { display: block; font-size: .75rem; margin-bottom: .375rem; color: var(--gray-500); }
            .project-dependencies .pd-summaries { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem; }
            .project-dependencies .pd-summary { display: grid; gap: .875rem; min-height: 7.5rem; align-content: start; }
            .project-dependencies .pd-version { font-size: 1.75rem; font-weight: 600; letter-spacing: -.025em; line-height: 1.25; overflow-wrap: anywhere; }
            .project-dependencies .pd-unconfirmed { color: var(--gray-400); font-size: 1.5rem; }
            .project-dependencies .pd-summary-meta, .project-dependencies .pd-inventory { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; font-size: .875rem; }
            .project-dependencies .pd-summary-meta > span { color: var(--gray-500); }
            .project-dependencies .pd-inventory { justify-content: space-between; }
            .project-dependencies .pd-inventory strong { font-size: 1.125rem; margin-right: .25rem; }
            .project-dependencies .pd-help { margin-top: -1rem; }
            .project-dependencies .pd-details { display: grid; gap: 1rem; }
            .project-dependencies .pd-details > div { display: grid; grid-template-columns: 11rem minmax(0, 1fr); gap: 1rem; font-size: .875rem; }
            .project-dependencies dd { overflow-wrap: anywhere; }
            .project-dependencies code { font-family: ui-monospace, monospace; font-size: .8125rem; }
            .project-dependencies .pd-empty { display: grid; justify-items: center; gap: 1rem; text-align: center; padding: 3rem 1rem; }
            .project-dependencies .pd-empty p { max-width: 35rem; }
            .project-dependencies .pd-empty-icon { width: 3rem; height: 3rem; color: var(--gray-400); }
            .dark .project-dependencies .pd-heading p, .dark .project-dependencies .pd-muted, .dark .project-dependencies dt, .dark .project-dependencies .pd-summary-meta > span, .dark .project-dependencies .pd-image-select label, .dark .project-dependencies .pd-empty p { color: var(--gray-400); }
            @media (max-width: 767px) {
                .project-dependencies .pd-heading { align-items: flex-start; flex-direction: column; }
                .project-dependencies .pd-image-select { width: 100%; max-width: none; }
                .project-dependencies .pd-summaries { grid-template-columns: 1fr; }
                .project-dependencies .pd-details > div { grid-template-columns: 1fr; gap: .25rem; }
            }
        </style>
    @endonce
</x-filament-panels::page>
