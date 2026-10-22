<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Pages\Concerns\HasProjectHeader;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
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

    #[Url(except: 'direct')]
    public string $scope = 'direct';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(ProjectResource::canView($this->getRecord()), 403);
    }

    #[Computed]
    public function comparisons(): Collection
    {
        return $this->getRecord()->dependencyComparisons();
    }

    #[Computed]
    public function comparison(): ?array
    {
        return $this->comparisons->get($this->image) ?? $this->comparisons->first();
    }

    #[Computed]
    public function rows(): Collection
    {
        $comparison = $this->comparison;

        return $comparison ? collect($comparison['build']->comparisonRows($comparison['production'])) : collect();
    }

    #[Computed]
    public function scopes(): array
    {
        return [
            'direct' => ['label' => 'Directes', 'count' => $this->rowsForScope('direct')->count()],
            'transitive' => ['label' => 'Transitives', 'count' => $this->rowsForScope('transitive')->count()],
            'dev' => ['label' => 'Développement', 'count' => $this->rowsForScope('dev')->count()],
            'all' => ['label' => 'Toutes', 'count' => $this->rows->count()],
        ];
    }

    public function updatedImage(): void
    {
        unset($this->comparison, $this->rows, $this->scopes);
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
            ->records(function (?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $rows = $this->rowsForScope($this->scope);

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
                TextColumn::make('required')->label('Déclaré')->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locked')->label('Lock')->fontFamily(FontFamily::Mono),
                TextColumn::make('installed')->label('Image construite')->badge()
                    ->color(fn (array $record): string => self::statusColor($record['build_status']))
                    ->tooltip(fn (array $record): string => $record['build_status']),
                TextColumn::make('production')->label('Production')->badge()
                    ->color(fn (array $record): string => self::statusColor($record['production_status']))
                    ->tooltip(fn (array $record): string => $record['production_status']),
                TextColumn::make('build_status')->label('Image / lock')->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Conforme au lock' => 'Conforme',
                        'Écart avec le lock' => 'Écart',
                        default => $state,
                    }),
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
            'Écart avec le lock', 'Non installée', 'Hors lock', 'Absente du relevé' => 'warning',
            default => 'gray',
        };
    }

    protected function rowsForScope(string $scope): Collection
    {
        return $this->rows->filter(fn (array $row): bool => match ($scope) {
            'direct' => $row['direct'] && ! $row['dev'],
            'transitive' => ! $row['direct'] && ! $row['dev'],
            'dev' => $row['dev'],
            default => true,
        });
    }
}
