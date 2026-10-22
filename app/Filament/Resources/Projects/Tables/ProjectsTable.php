<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Actions\ProjectMaintenanceActions;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'dokploy',
                'latestDependencyReport' => fn ($report) => $report->select([
                    'dependency_reports.id', 'dependency_reports.project_id', 'dependency_reports.language_version', 'dependency_reports.built_at',
                ]),
            ])->withCount('stagings'))
            ->defaultSort('app_name')
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Vos projets au même endroit')
            ->emptyStateDescription('Ajoutez un projet pour gérer sa maintenance et ses environnements de staging.')
            ->columns([
                TextColumn::make('app_name')->label('Projet')->searchable()->sortable()->weight('bold')
                    ->description(fn (Project $record): ?string => $record->github_owner && $record->github_repository ? $record->github_owner.'/'.$record->github_repository : null),
                TextColumn::make('maintenance_status')->label('Statut du site')->badge()
                    ->state(fn (Project $record): string => $record->maintenanceStatusLabel())
                    ->color(fn (Project $record): string => $record->maintenanceStatusColor())
                    ->description(fn (Project $record): string => $record->maintenance_checked_at
                        ? $record->maintenance_checked_at->diffForHumans()
                        : 'Jamais vérifié'),
                TextColumn::make('stagings_count')->label('Stagings')->badge()->sortable()
                    ->url(fn (Project $record): string => ProjectResource::getUrl('stagings', ['record' => $record])),
                TextColumn::make('latestDependencyReport.language_version')->label('Langage · dernier build')->badge()
                    ->placeholder('Aucun relevé')->toggleable()
                    ->description(fn (Project $record): ?string => $record->latestDependencyReport?->built_at->diffForHumans()),
                TextColumn::make('domain_name')->label('Domaine de staging')->searchable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('dokploy_id')->label('Connexion Dokploy')->relationship('dokploy', 'base_url')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->connectionLabel())->searchable()->preload(),
                TernaryFilter::make('maintenance_enabled')->label('Dernier état de maintenance')->nullable()
                    ->trueLabel('En maintenance')->falseLabel('En ligne'),
            ])
            ->recordActions([
                ViewAction::make()->label('Ouvrir'),
                ActionGroup::make(ProjectMaintenanceActions::all())->label('Maintenance')->icon('heroicon-o-wrench-screwdriver'),
                EditAction::make()->label('Réglages')->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
