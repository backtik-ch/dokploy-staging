<?php

namespace App\Filament\Pages;

use App\Exceptions\LokiException;
use App\Filament\Tables\LogsTable;
use App\Filament\Widgets\Logs\LogStats;
use App\Services\Loki\LogQueryFactory;
use App\Services\Loki\LogResult;
use App\Services\Loki\LokiService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Logs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $title = 'Logs';

    protected static ?int $navigationSort = 2;

    protected ?string $subheading = 'Explorez les événements de vos projets et remontez à la source d’un incident.';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.logs';

    #[Url]
    public array $filters = [
        'period' => '1h', 'project' => null, 'service' => null, 'environment' => null,
        'level' => null, 'labels' => [], 'limit' => 1000, 'case_sensitive' => false,
        'start' => null, 'end' => null,
    ];

    #[Locked]
    public string $endAt;

    public bool $live = false;

    public bool $wrap = true;

    public bool $showActivity = false;

    #[Url(as: 'search')]
    public $tableSearch = '';

    protected ?string $labelDiscoveryError = null;

    public function mount(): void
    {
        $this->endAt = CarbonImmutable::now('UTC')->toIso8601String();
        $this->filtersForm->fill($this->filters);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->statePath('filters')->components([
            Section::make('Explorer les logs')
                ->description('Combinez une période, un projet et ses labels. La recherche porte sur le corps complet du log dans Loki.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->compact()
                ->columns(['default' => 1, 'md' => 3, 'xl' => 5])
                ->schema([
                    Select::make('period')->label('Période')->options(LogQueryFactory::PERIODS)->required()->selectablePlaceholder(false)->native(false)->live(),
                    $this->labelSelect('project', 'Projet', config('loki.project_label')),
                    $this->labelSelect('environment', 'Environnement', 'env'),
                    $this->labelSelect('service', 'Service', 'service'),
                    $this->labelSelect('level', 'Niveau', 'level'),
                    DateTimePicker::make('start')->label('Du (UTC)')->timezone('UTC')->native(false)->live()
                        ->visible(fn (Get $get): bool => $get('period') === 'custom')->required(fn (Get $get): bool => $get('period') === 'custom'),
                    DateTimePicker::make('end')->label('Au (UTC)')->timezone('UTC')->native(false)->live()
                        ->visible(fn (Get $get): bool => $get('period') === 'custom')->required(fn (Get $get): bool => $get('period') === 'custom'),
                    Section::make('Labels et options')
                        ->description('Affinez par hôte, conteneur ou tout autre label Loki.')
                        ->collapsible()->collapsed()->columnSpanFull()->columns(3)
                        ->schema([
                            Repeater::make('labels')->label('Labels supplémentaires')->columnSpanFull()
                                ->schema([
                                    Select::make('name')->label('Label')->options(fn (): array => $this->labelOptions())
                                        ->searchable()->native(false)->live()->required()
                                        ->afterStateUpdated(fn (Set $set) => $set('value', null)),
                                    Select::make('value')->label('Valeur exacte')
                                        ->options(fn (Get $get): array => filled($get('name')) ? $this->labelOptions($get('name')) : [])
                                        ->searchable()->native(false)->live()->required(),
                                ])->columns(2)->defaultItems(0)->maxItems(20)->reorderable(false)->addActionLabel('Ajouter un label')->live(),
                            Select::make('limit')->label('Lignes à charger')->options([500 => '500', 1000 => '1 000', 2500 => '2 500', 5000 => '5 000'])
                                ->native(false)->required()->live(),
                            Toggle::make('case_sensitive')->label('Recherche sensible à la casse')->live()->inline(false),
                        ]),
                ]),
        ]);
    }

    protected function labelSelect(string $field, string $title, string $label): Select
    {
        return Select::make($field)->label($title)->placeholder('Tous')
            ->options(fn (): array => $this->labelOptions($label))
            ->searchable()->native(false)->live();
    }

    public function labelOptions(?string $label = null): array
    {
        if ($this->labelDiscoveryError || $this->result->error) {
            return [];
        }

        try {
            $query = app(LogQueryFactory::class)->make($this->filters, $this->endAt, exceptLabel: $label);
            $service = app(LokiService::class);

            return $label === null ? $service->labels($query) : $service->labelValues($label, $query);
        } catch (LokiException $exception) {
            $this->labelDiscoveryError = $exception->getMessage();

            return [];
        }
    }

    public function getLabelDiscoveryError(): ?string
    {
        return $this->labelDiscoveryError;
    }

    #[Computed]
    public function result(): LogResult
    {
        try {
            $query = app(LogQueryFactory::class)->make($this->filters, $this->endAt, (string) $this->getTableSearch());

            return app(LokiService::class)->search($query);
        } catch (LokiException $exception) {
            return new LogResult(collect(), error: $exception->getMessage());
        }
    }

    public function table(Table $table): Table
    {
        return LogsTable::configure($table, $this);
    }

    public function updatedFilters(mixed $value, ?string $key = null): void
    {
        if ($this->filters['period'] === 'custom') {
            $this->live = false;
        }

        if ($key === 'project') {
            $this->filters['service'] = null;
            $this->filters['environment'] = null;
            $this->filters['level'] = null;
            $this->filters['labels'] = [];
        }

        $this->refreshLogs();
    }

    public function updatedTableSearch(): void
    {
        $this->refreshLogs();
    }

    public function refreshLogs(): void
    {
        $this->endAt = CarbonImmutable::now('UTC')->toIso8601String();
        unset($this->result);
        $this->resetPage($this->getTablePaginationPageName());
        $this->flushCachedTableRecords();
    }

    public function poll(): void
    {
        if ($this->live && $this->filters['period'] !== 'custom' && $this->getTablePage() === 1 && empty($this->mountedActions)) {
            $this->refreshLogs();
        }
    }

    public function isolateStream(array $labels): void
    {
        $this->filters['project'] = null;
        $this->filters['environment'] = null;
        $this->filters['service'] = null;
        $this->filters['level'] = null;
        $this->filters['labels'] = array_map(fn ($name, $value): array => ['name' => $name, 'value' => $value], array_keys($labels), array_values($labels));
        $this->refreshLogs();
    }

    public function export(): StreamedResponse
    {
        $entries = $this->result->entries;

        return response()->streamDownload(function () use ($entries): void {
            foreach ($entries as $entry) {
                echo json_encode([
                    'timestamp' => $entry['timestamp'], 'datetime' => $entry['datetime'],
                    'labels' => $entry['labels'], 'metadata' => $entry['metadata'], 'line' => $entry['raw'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
            }
        }, 'logs-'.now('UTC')->format('Ymd-His').'.ndjson', ['Content-Type' => 'application/x-ndjson']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetFilters')->label('Réinitialiser')->icon('heroicon-o-x-mark')->color('gray')
                ->action(function (): void {
                    $this->reset('filters', 'tableSearch');
                    $this->filtersForm->fill($this->filters);
                    $this->refreshLogs();
                }),
            Action::make('toggleLive')->label(fn (): string => $this->live ? 'Suivi actif · 10 s' : 'Suivre les logs')
                ->icon(fn (): string => $this->live ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn (): string => $this->live ? 'success' : 'gray')
                ->disabled(fn (): bool => $this->filters['period'] === 'custom')
                ->action(function (): void {
                    $this->live = ! $this->live;
                    if ($this->live) {
                        $this->refreshLogs();
                    }
                }),
            Action::make('refreshLogs')->label('Actualiser')->icon('heroicon-o-arrow-path')->action('refreshLogs'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [LogStats::class];
    }

    public function getWidgetData(): array
    {
        return ['summary' => $this->result->summary(), 'unavailable' => $this->result->error !== null];
    }
}
