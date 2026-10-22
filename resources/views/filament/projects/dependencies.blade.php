@php
    $comparisons = $getRecord()->dependencyComparisons();
@endphp

@forelse ($comparisons as $comparison)
    @php
        $build = $comparison['build'];
        $production = $comparison['production'];
        $rows = collect($build->comparisonRows($production));
    @endphp
    <div class="space-y-4" style="margin-bottom: 2rem">
        <div class="space-y-1 text-sm">
            <p class="font-semibold break-all">{{ $build->image }} · {{ $build->platform }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ $build->repository }} · {{ $build->branch }} · commit {{ substr($build->commit_sha, 0, 12) }}
            </p>
            <p class="text-gray-500 dark:text-gray-400">
                Dernier build : {{ $build->built_at->format('d/m/Y H:i:s T') }}
                @if ($build->built_at->lt(now()->subDay()))
                    · Relevé de plus de 24 h
                @endif
            </p>
            <p class="text-xs break-all">Image construite : {{ $build->digest }}</p>
            @if ($production)
                <p class="text-xs break-all">Production : {{ $production->digest }} · commit {{ substr($production->commit_sha, 0, 12) }} · confirmée le {{ $production->production_confirmed_at->format('d/m/Y H:i:s T') }}</p>
            @else
                <p class="text-gray-500 dark:text-gray-400">Production non confirmée. Aucun digest déployé n’a encore été signalé pour cette image.</p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="border-collapse: collapse; min-width: 640px">
                <caption class="sr-only">Comparaison du langage et des dépendances directes pour {{ $build->image }}</caption>
                <thead>
                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                        @foreach (['Élément', 'Déclaré', 'Lock du build', 'Image construite', 'Production', 'Comparaison'] as $heading)
                            <th scope="col" style="padding: 0.75rem 0.5rem">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th scope="row" class="text-left" style="padding: 0.75rem 0.5rem">Langage</th>
                        <td style="padding: 0.75rem 0.5rem">—</td>
                        <td style="padding: 0.75rem 0.5rem">—</td>
                        <td style="padding: 0.75rem 0.5rem">{{ $build->language_version }}</td>
                        <td style="padding: 0.75rem 0.5rem">{{ $production?->language_version ?? 'Non confirmée' }}</td>
                        <td style="padding: 0.75rem 0.5rem">{{ $production ? ($production->language_version === $build->language_version ? 'Même version' : 'Versions différentes') : 'Production non confirmée' }}</td>
                    </tr>
                    @include('filament.projects.dependency-rows', ['rows' => $rows->where('direct', true)->where('dev', false)])
                </tbody>
            </table>
        </div>
        @if ($rows->contains(fn ($row) => ! $row['direct'] || $row['dev']))
            <details>
                <summary class="cursor-pointer text-sm font-medium">Dépendances transitives et de développement</summary>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="border-collapse: collapse; min-width: 640px">
                        <caption class="sr-only">Dépendances transitives et de développement</caption>
                        <thead>
                            <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                                @foreach (['Élément', 'Déclaré', 'Lock du build', 'Image construite', 'Production', 'Comparaison'] as $heading)
                                    <th scope="col" style="padding: 0.75rem 0.5rem">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @include('filament.projects.dependency-rows', ['rows' => $rows->filter(fn ($row) => ! $row['direct'] || $row['dev'])])
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>
@empty
    <div class="space-y-2 text-sm text-gray-500 dark:text-gray-400">
        <p>Aucun rapport de dépendances reçu.</p>
        <p>Activez le relevé dans le build GitHub Actions avec l’identifiant du projet {{ $getRecord()->id }}. Les versions apparaîtront ici après le premier build sur main.</p>
    </div>
@endforelse
