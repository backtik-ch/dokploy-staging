<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Pages\Concerns\HasProjectHeader;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\DependencyReport;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ViewProjectDependencies extends Page implements HasTable
{
    use HasProjectHeader;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ProjectResource::class;

    protected static ?string $navigationLabel = 'Dépendances';

    protected static ?string $breadcrumb = 'Dépendances';

    protected string $view = 'filament.projects.dependencies';

    #[Url]
    public ?string $image = null;

    #[Url(except: 'all')]
    public string $scope = 'all';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(ProjectResource::canView($this->getRecord()), 403);
    }

    #[Computed]
    public function reports(): Collection
    {
        return $this->getRecord()->latestDependencyReportsByImage();
    }

    #[Computed]
    public function report(): ?DependencyReport
    {
        return $this->reports->get($this->image) ?? $this->reports->first();
    }

    #[Computed]
    public function rows(): Collection
    {
        return collect($this->report?->comparisonRows() ?? [])
            ->map(fn (array $row): array => ['__key' => (string) $row['id'], ...$row]);
    }

    #[Computed]
    public function scopes(): array
    {
        return [
            'risks' => ['label' => 'À risques', 'count' => $this->rowsForScope('risks')->count()],
            'direct' => ['label' => 'Directes', 'count' => $this->rowsForScope('direct')->count()],
            'transitive' => ['label' => 'Transitives', 'count' => $this->rowsForScope('transitive')->count()],
            'dev' => ['label' => 'Développement', 'count' => $this->rowsForScope('dev')->count()],
            'all' => ['label' => 'Toutes', 'count' => $this->rows->count()],
        ];
    }

    public function updatedImage(): void
    {
        unset($this->report, $this->rows, $this->scopes);
        $this->resetPage($this->getTablePaginationPageName());
        $this->flushCachedTableRecords();
    }

    public function updatedScope(): void
    {
        $this->resetPage($this->getTablePaginationPageName());
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int $page, int $recordsPerPage, ?array $filters): LengthAwarePaginator {
                $rows = $this->rowsForScope($this->scope);

                if (filled($manager = $filters['manager']['value'] ?? null)) {
                    $rows = $rows->where('manager', $manager);
                }

                if (filled($search)) {
                    $terms = preg_split('/\s+/u', mb_strtolower(trim($search)));
                    $rows = $rows->filter(function (array $row) use ($terms): bool {
                        $text = mb_strtolower(implode(' ', [$row['name'], $row['manager'], $row['path']]));

                        return collect($terms)->every(fn (string $term): bool => str_contains($text, $term));
                    });
                }

                return new LengthAwarePaginator(
                    $rows->values()->forPage($page, $recordsPerPage),
                    $rows->count(),
                    $recordsPerPage,
                    $page,
                    ['pageName' => $this->getTablePaginationPageName()],
                );
            })
            ->columns([
                TextColumn::make('name')->label('Dépendance')->weight(FontWeight::Medium)->searchable()
                    ->description(fn (array $record): string => implode(' · ', array_filter([$record['manager'], $record['dev'] ? 'Développement' : null])))
                    ->tooltip(fn (array $record): ?string => $record['path'])->wrap(),
                TextColumn::make('manager')->label('Écosystème')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state === 'composer' ? 'Composer' : ($state ?? 'Autre')),
                TextColumn::make('risk_label')->label('Sécurité')->badge()
                    ->color(fn (array $record): string => $record['risk_color'])
                    ->description(fn (array $record): ?string => $record['risk_count'] ? $record['risk_count'].' problème(s)' : null)
                    ->tooltip(fn (array $record): string => $record['audited_at'] ? 'Dernier audit réussi : '.$record['audited_at'] : 'Aucun audit réussi pour cette dépendance'),
                TextColumn::make('required')->label('Déclaré')->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locked')->label('Lock')->fontFamily(FontFamily::Mono),
                TextColumn::make('installed')->label('Image construite')->badge()
                    ->color(fn (array $record): string => self::statusColor($record['build_status']))
                    ->tooltip(fn (array $record): string => $record['build_status']),
                TextColumn::make('build_status')->label('Image / lock')->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Conforme au lock' => 'Conforme',
                        'Écart avec le lock' => 'Écart',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('manager')->label('Écosystème')
                    ->options(fn (): array => $this->rows->pluck('manager')->filter()->unique()->mapWithKeys(fn (string $manager): array => [$manager => $manager === 'composer' ? 'Composer (PHP)' : $manager])->all()),
            ])
            ->deferFilters(false)
            ->recordClasses(fn (array $record): ?string => $record['risk_count'] ? 'pd-risk-row' : null)
            ->recordActions([
                Action::make('risks')->label('Voir les risques')->icon('heroicon-o-shield-exclamation')
                    ->visible(fn (array $record): bool => $record['risk_count'] > 0)
                    ->modalHeading(fn (array $record): string => $record['name'])
                    ->modalContent(fn (array $record) => view('filament.projects.dependency-risks', ['record' => $record]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),
            ])
            ->searchPlaceholder('Rechercher une dépendance…')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('Aucune dépendance à afficher')
            ->emptyStateDescription('Choisissez une autre catégorie ou modifiez votre recherche.');
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'Conforme au lock' => 'success',
            'Écart avec le lock', 'Non installée', 'Hors lock' => 'warning',
            default => 'gray',
        };
    }

    protected function rowsForScope(string $scope): Collection
    {
        return $this->rows->filter(fn (array $row): bool => match ($scope) {
            'risks' => $row['risk_count'] > 0,
            'direct' => $row['direct'] && ! $row['dev'],
            'transitive' => ! $row['direct'] && ! $row['dev'],
            'dev' => $row['dev'],
            default => true,
        });
    }
}
