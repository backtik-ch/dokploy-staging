<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Actions\ProjectMaintenanceActions;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Maintenance du site')
                ->key('site-maintenance')
                ->description('Statut Traefik mis en cache pendant 3 minutes. Utilisez l’icône d’actualisation pour forcer une vérification.')
                ->headerActions([
                    ProjectMaintenanceActions::refresh()->iconButton()->tooltip('Actualiser le statut sans cache'),
                ])
                ->icon('heroicon-o-wrench-screwdriver')
                ->schema([
                    TextEntry::make('maintenance_status')->label('Statut de maintenance')
                        ->state(fn (Project $record): string => $record->maintenanceStatusLabel())
                        ->badge()->color(fn (Project $record): string => $record->maintenanceStatusColor()),
                    TextEntry::make('maintenance_checked_at')->label('Dernière vérification')->dateTime('d/m/Y H:i:s T')->placeholder('Jamais vérifiée'),
                    TextEntry::make('maintenance_yml_file')->label('Fichier Traefik')->placeholder('Configurez le fichier YAML dans les réglages du projet.')->copyable(),
                    TextEntry::make('maintenance_error')->label('Dernière erreur')->color('danger')->columnSpanFull()
                        ->visible(fn (Project $record): bool => filled($record->maintenance_error)),
                ])->columns(2)->columnSpanFull(),
            Section::make('Infrastructure')->icon('heroicon-o-server-stack')->schema([
                TextEntry::make('app_name')->label('Projet'),
                TextEntry::make('dokploy_connection')->label('Connexion Dokploy')->state(fn (Project $record): string => $record->dokploy?->connectionLabel() ?? 'Connexion supprimée'),
                TextEntry::make('server_id')->label('Serveur Dokploy')->placeholder('Serveur local'),
                TextEntry::make('dokploy_project_id')->label('ID du projet Dokploy')->placeholder('Non renseigné')->copyable(),
            ])->columns(2),
            Section::make('Stagings')->icon('heroicon-o-beaker')->schema([
                TextEntry::make('stagings_count')->label('Environnements de staging')
                    ->state(fn (Project $record): int => $record->stagings()->count())
                    ->url(fn (Project $record): string => ProjectResource::getUrl('stagings', ['record' => $record])),
                TextEntry::make('repository')->label('Repository principal')
                    ->state(fn (Project $record): ?string => $record->github_owner && $record->github_repository ? $record->github_owner.'/'.$record->github_repository : null)
                    ->placeholder('Non configuré'),
                TextEntry::make('domain_name')->label('Domaine des stagings')->placeholder('Non configuré'),
            ])->columns(2),
        ]);
    }
}
