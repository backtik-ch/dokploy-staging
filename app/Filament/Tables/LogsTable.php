<?php

namespace App\Filament\Tables;

use App\Filament\Pages\Logs;
use App\Services\Loki\LogEntry;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class LogsTable
{
    public static function configure(Table $table, Logs $livewire): Table
    {
        return $table
            ->heading('Journal des événements')
            ->description('Heures en UTC · Cliquez sur une ligne pour lire le message complet et ses labels.')
            ->records(function (int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection) use ($livewire): LengthAwarePaginator {
                $rows = $livewire->result->entries;

                if (in_array($sortColumn, ['timestamp', 'level', 'project'])) {
                    $rows = $sortDirection === 'asc' ? $rows->sortBy($sortColumn, SORT_STRING) : $rows->sortByDesc($sortColumn, SORT_STRING);
                }

                return new LengthAwarePaginator($rows->keyBy('id')->forPage($page, $recordsPerPage), $rows->count(), $recordsPerPage, $page, [
                    'pageName' => $livewire->getTablePaginationPageName(),
                ]);
            })
            ->columns([
                TextColumn::make('timestamp')->label('Heure · UTC')->sortable()->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (array $record): string => $record['time'])
                    ->description(fn (array $record): string => substr($record['datetime'], 0, 10))
                    ->tooltip(fn (array $record): string => $record['timestamp'].' ns'),
                TextColumn::make('level')->label('Niveau')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => $state === 'unknown' ? '—' : strtoupper($state))
                    ->color(fn (string $state): string => LogEntry::color($state)),
                TextColumn::make('project')->label('Projet')->sortable()->weight('medium')->toggleable(),
                TextColumn::make('message')->label('Message')->fontFamily(FontFamily::Mono)->searchable()
                    ->wrap(fn (): bool => $livewire->wrap)->lineClamp(fn (): int => $livewire->wrap ? 3 : 1),
                TextColumn::make('environment')->label('Environnement')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('timestamp', 'desc')
            ->searchPlaceholder('Chercher dans le corps des logs…')
            ->searchDebounce('600ms')
            ->paginated([25, 50, 100])->defaultPaginationPageOption(50)
            ->striped()
            ->recordAction('details')
            ->recordActions([
                Action::make('details')->label('Lire')->icon('heroicon-o-document-magnifying-glass')->iconButton()->tooltip('Lire le log complet')->color('gray')
                    ->slideOver()->modalWidth(Width::FiveExtraLarge)->modalHeading('Détail du log')
                    ->modalDescription(fn (?array $record): ?string => $record ? $record['datetime'].' · '.$record['project'].' · '.$record['service'] : null)
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer')
                    ->schema([
                        Section::make()->columns(['default' => 1, 'sm' => 3])->schema([
                            TextEntry::make('level')->label('Niveau')->badge()->color(fn (string $state): string => LogEntry::color($state)),
                            TextEntry::make('project')->label('Projet'),
                            TextEntry::make('service')->label('Service')->copyable(),
                        ]),
                        Tabs::make('Contenu du log')->tabs([
                            Tab::make('Message')->icon('heroicon-o-document-text')->schema([
                                TextEntry::make('pretty')->label('Corps complet')->fontFamily(FontFamily::Mono)->copyable()
                                    ->extraAttributes(['style' => 'white-space: pre-wrap; overflow-wrap: anywhere; font-size: .8125rem;']),
                            ]),
                            Tab::make('Brut')->icon('heroicon-o-code-bracket')->schema([
                                TextEntry::make('raw')->label('Ligne originale')->fontFamily(FontFamily::Mono)->copyable()
                                    ->extraAttributes(['style' => 'white-space: pre-wrap; overflow-wrap: anywhere; font-size: .8125rem;']),
                                TextEntry::make('timestamp')->label('Horodatage en nanosecondes')->copyable()->fontFamily(FontFamily::Mono),
                            ]),
                            Tab::make('Labels')->icon('heroicon-o-tag')->schema([
                                KeyValueEntry::make('labels')->label('Labels du flux')->keyLabel('Label')->valueLabel('Valeur'),
                                KeyValueEntry::make('metadata')->label('Métadonnées structurées')->keyLabel('Champ')->valueLabel('Valeur'),
                            ]),
                        ]),
                    ]),
                Action::make('isolate')->label('Isoler le flux')->icon('heroicon-o-funnel')->iconButton()->tooltip('Isoler ce flux de logs')->color('gray')
                    ->action(fn (array $record) => $livewire->isolateStream($record['labels'])),
            ])
            ->headerActions([
                Action::make('activity')->label(fn (): string => $livewire->showActivity ? 'Masquer l’activité' : 'Activité')
                    ->icon('heroicon-o-chart-bar')->color('gray')->action(fn () => $livewire->showActivity = ! $livewire->showActivity),
                Action::make('wrap')->label(fn (): string => $livewire->wrap ? 'Vue compacte' : 'Déplier les messages')
                    ->icon('heroicon-o-bars-3-bottom-left')->color('gray')->action(fn () => $livewire->wrap = ! $livewire->wrap),
                Action::make('export')->label('Exporter')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->disabled(fn (): bool => $livewire->result->entries->isEmpty())
                    ->action(fn () => $livewire->export()),
            ])
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->emptyStateHeading(fn (): string => $livewire->result->error ? 'Connexion à Loki indisponible' : 'Aucun log pour cette sélection')
            ->emptyStateDescription(fn (): string => $livewire->result->error ? 'Les logs s’afficheront dès que la connexion sera rétablie.' : 'Élargissez la période, retirez un label ou modifiez votre recherche.');
    }
}
