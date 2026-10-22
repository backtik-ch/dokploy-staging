<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Projects\Resources\Stagings\Schemas\StagingForm;
use App\Filament\Resources\Projects\Resources\Stagings\StagingResource;
use App\Models\Project;
use App\Services\DeployService;
use App\Services\GithubService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

class CreateProjectStagingAction
{
    public static function make(): Action
    {
        return Action::make('createStaging')
            ->label('Créer un staging')
            ->icon('heroicon-o-plus')
            ->authorize(fn (): bool => StagingResource::canCreate())
            ->disabled(fn (Project $record): bool => ! $record->hasStagingConfiguration())
            ->tooltip(fn (Project $record): ?string => $record->hasStagingConfiguration() ? null : 'Renseignez la configuration des stagings dans les réglages du projet.')
            ->modalHeading(fn (Project $record): string => 'Créer un staging · '.$record->app_name)
            ->modalSubmitActionLabel('Créer le staging')
            ->beforeFormFilled(fn (Project $record) => app(GithubService::class)->warmProjectBranchesCache($record))
            ->schema(fn (Schema $schema): Schema => StagingForm::configure($schema))
            ->action(function (Project $record, array $data): void {
                app(DeployService::class)->deploy(
                    $record,
                    'create',
                    (string) $data['staging_reference'],
                    (string) $data['branch'],
                    $data['selected_branches'] ?? [],
                    true,
                );

                Notification::make()->success()->title('Staging créé')
                    ->body('Le déploiement attend les images configurées avant de démarrer.')->send();
            });
    }
}
