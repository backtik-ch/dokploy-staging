<x-filament-panels::page>
    <div class="project-dependencies">
        @if ($build = $this->report)
            @php
                $differences = $this->rows->where('dev', false)->filter(fn ($row) => \App\Filament\Resources\Projects\Pages\ViewProjectDependencies::statusColor($row['build_status']) === 'warning')->count();
                $riskCount = $this->rows->where('risk_count', '>', 0)->count();
            @endphp

            <div class="pd-heading">
                <div>
                    <h2>Dépendances</h2>
                    <p>Comparez les versions du lock et celles de l’image construite sur main.</p>
                </div>
                @if ($this->reports->count() > 1)
                    <div class="pd-image-select">
                        <label for="dependency-image">Image et plateforme</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="dependency-image" wire:model.live="image">
                                @foreach ($this->reports as $key => $item)
                                    <option value="{{ $key }}">{{ $item->image }} · {{ $item->platform }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @else
                    <x-filament::badge color="gray" icon="heroicon-o-cube">{{ $build->platform }}</x-filament::badge>
                @endif
            </div>

            <x-filament::section icon="heroicon-o-cube" heading="Dernière image construite sur main" compact>
                <div class="pd-summary">
                    <div>
                        <p class="pd-muted">Langage</p>
                        <p class="pd-version">{{ $build->language_version }}</p>
                    </div>
                    <div class="pd-build-details">
                        <div class="pd-summary-meta">
                            <x-filament::badge color="primary">{{ $build->branch }}</x-filament::badge>
                            <span title="{{ $build->built_at->format('d/m/Y H:i:s T') }}">{{ $build->built_at->diffForHumans() }}</span>
                        </div>
                        <p class="pd-muted">{{ $build->built_at->format('d/m/Y à H:i') }} · <code>{{ substr($build->commit_sha, 0, 8) }}</code></p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Audit de sécurité" icon="heroicon-o-shield-check" compact>
                <div class="pd-audit-summary">
                    <x-filament::badge :color="$riskCount ? 'danger' : 'gray'" :icon="$riskCount ? 'heroicon-o-shield-exclamation' : 'heroicon-o-shield-check'">
                        {{ $riskCount }} dépendance(s) signalée(s)
                    </x-filament::badge>
                    <p class="pd-muted">Contrôle horaire · Les problèmes détectés restent visibles, même lorsqu’une notification est limitée à une fois par 24 h.</p>
                </div>
                <div class="pd-audit-managers">
                    @foreach ($this->rows->pluck('manager')->unique()->filter(fn ($manager) => in_array($manager, ['composer', 'npm'], true)) as $manager)
                        @php
                            $audit = $build->audits->firstWhere('manager', $manager);
                            $managerRows = $this->rows->where('manager', $manager);
                            $managerRisks = $managerRows->where('risk_count', '>', 0)->count();
                            $pending = $managerRows->filter(fn ($row) => $row['audit_version'] !== null && $row['audited_at'] === null)->count();
                        @endphp
                        <div class="pd-audit-manager">
                            <div class="pd-summary-meta">
                                <strong>{{ $manager === 'composer' ? 'Composer · PHP' : 'npm · JavaScript' }}</strong>
                                <x-filament::badge :color="$audit?->error ? 'warning' : ($managerRisks ? 'danger' : ($audit?->audited_at && ! $pending ? 'success' : 'gray'))">
                                    {{ $audit?->error ? 'Audit en erreur' : ($managerRisks ? $managerRisks.' à risques' : ($pending ? $pending.' en attente d’audit' : ($audit?->audited_at ? 'Aucun risque détecté' : 'En attente d’audit'))) }}
                                </x-filament::badge>
                            </div>
                            <p class="pd-muted">
                                {{ $managerRows->count() }} dépendance(s) ·
                                {{ $audit?->audited_at ? 'Dernier audit réussi : '.$audit->audited_at->format('d/m/Y à H:i') : 'Les résultats apparaîtront après le prochain audit horaire.' }}
                            </p>
                            @if ($audit?->error)
                                <details class="pd-muted"><summary>Afficher l’erreur du dernier audit</summary><p class="pd-audit-error">{{ $audit->error }}</p></details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

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
                        :badge-color="$key === 'risks' && $category['count'] ? 'danger' : 'gray'"
                        wire:click="$set('scope', '{{ $key }}')"
                    >
                        {{ $category['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <div wire:key="dependency-table-{{ $build->id }}">
                {{ $this->table }}
            </div>
            <p class="pd-muted pd-help">La colonne Sécurité affiche les résultats d’audit. Image / lock compare les versions. Pour un frontend compilé, les versions npm du lock peuvent être auditées sans Node dans l’image.</p>

            <x-filament::section heading="Détails de l’image" icon="heroicon-o-finger-print" collapsible collapsed compact>
                <dl class="pd-details">
                    <div><dt>Image</dt><dd>{{ $build->image }}</dd></div>
                    <div><dt>Plateforme</dt><dd>{{ $build->platform }}</dd></div>
                    <div><dt>Dépôt</dt><dd>{{ $build->repository }}</dd></div>
                    <div><dt>Commit du build</dt><dd><code>{{ $build->commit_sha }}</code></dd></div>
                    <div><dt>Digest du build</dt><dd><code>{{ $build->digest }}</code></dd></div>
                </dl>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="pd-empty">
                    <x-filament::icon icon="heroicon-o-cube-transparent" class="pd-empty-icon" />
                    <h2>Les versions de votre projet, au même endroit</h2>
                    <p>Le premier rapport affichera le langage et les dépendances de l’image construite sur main, avec une comparaison des versions du lock.</p>
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
            .project-dependencies .pd-summary { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; }
            .project-dependencies .pd-build-details { display: grid; gap: .875rem; }
            .project-dependencies .pd-version { font-size: 1.75rem; font-weight: 600; letter-spacing: -.025em; line-height: 1.25; overflow-wrap: anywhere; }
            .project-dependencies .pd-summary-meta, .project-dependencies .pd-inventory { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; font-size: .875rem; }
            .project-dependencies .pd-summary-meta > span { color: var(--gray-500); }
            .project-dependencies .pd-inventory { justify-content: space-between; }
            .project-dependencies .pd-inventory strong { font-size: 1.125rem; margin-right: .25rem; }
            .project-dependencies .pd-help { margin-top: -1rem; }
            .project-dependencies .pd-audit-summary { display: grid; justify-items: start; gap: .75rem; margin-bottom: 1rem; }
            .project-dependencies .pd-audit-managers { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1.25rem; }
            .project-dependencies .pd-audit-manager { display: grid; align-content: start; gap: .5rem; }
            .project-dependencies .pd-audit-error { white-space: pre-wrap; overflow-wrap: anywhere; margin-top: .5rem; }
            .project-dependencies .pd-risk-row > td:first-child { box-shadow: inset 3px 0 0 var(--danger-500); }
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
                .project-dependencies .pd-details > div { grid-template-columns: 1fr; gap: .25rem; }
            }
        </style>
    @endonce
</x-filament-panels::page>
