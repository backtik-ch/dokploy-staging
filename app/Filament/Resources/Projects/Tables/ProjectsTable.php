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
            ->modifyQueryUsing(fn ($query) => $query->with('dokploy')->withCount('stagings'))
            ->defaultSort('app_name')
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Vos projets au même endroit')
            ->emptyStateDescription('Ajoutez un projet pour gérer sa maintenance et ses environnements de staging.')
            ->columns([
                TextColumn::make('app_name')->label('Projet')->searchable()->sortable()->weight('bold')
                    ->description(fn (Project $record): ?string => $record->github_owner && $record->github_repository ? $record->github_owner.'/'.$record->github_repository : null),
                TextColumn::make('maintenance_status')->label('Maintenance')->badge()
                    ->state(fn (Project $record): string => $record->maintenanceStatusLabel())
                    ->color(fn (Project $record): string => $record->maintenanceStatusColor()),
                TextColumn::make('maintenance_checked_at')->label('Dernière vérification')->since()->placeholder('Jamais')
                    ->dateTimeTooltip()->sortable(),
                TextColumn::make('stagings_count')->label('Stagings')->badge()->sortable()
                    ->url(fn (Project $record): string => ProjectResource::getUrl('stagings', ['record' => $record])),
                TextColumn::make('dokploy.name')->label('Connexion Dokploy')
                    ->state(fn (Project $record): string => $record->dokploy ? ($record->dokploy->name ?: 'Connexion').' · #'.$record->dokploy_id : 'Connexion supprimée')
                    ->description(fn (Project $record): ?string => $record->dokploy?->base_url),
                TextColumn::make('domain_name')->label('Domaine de staging')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('server_id')->label('Serveur')->placeholder('Local')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('dokploy_id')->label('Connexion Dokploy')->relationship('dokploy', 'base_url')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->connectionLabel())->searchable()->preload(),
                TernaryFilter::make('maintenance_enabled')->label('Dernier état de maintenance')->nullable()
                    ->trueLabel('En maintenance')->falseLabel('Maintenance désactivée'),
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
