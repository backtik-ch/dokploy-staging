<?php

namespace App\Filament\Resources\Projects\Resources\Stagings\Tables;

use App\Models\Staging;
use App\Services\DeployService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StagingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('pr_number')
                    ->label('Référence')->searchable(),
                TextColumn::make('branch')->label('Branche principale')->searchable(),
                TextColumn::make('deploy_status')
                    ->label('Déploiement')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'waiting_for_images' => 'En attente des images',
                        'deploying' => 'En cours',
                        'deployed' => 'Déployé',
                        'failed' => 'Échec',
                        default => $state ?? 'Non suivi',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'deployed' => 'success',
                        'failed' => 'danger',
                        'waiting_for_images', 'deploying' => 'warning',
                        default => 'gray',
                    })
                    ->badge(),
                TextColumn::make('selected_branches')
                    ->label('Branches liées')
                    ->formatStateUsing(function ($state) {
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);
                            $state = is_array($decoded) ? $decoded : [];
                        }

                        if (! is_array($state)) {
                            $state = [];
                        }

                        return collect($state)
                            ->map(fn ($branch, $placeholder) => $placeholder.': '.$branch)
                            ->implode(' | ');
                    }),
            ])
            ->recordActions([
                Action::make('deploy')
                    ->label('Déployer')
                    ->color('success')
                    ->action(fn (Staging $record) => app(DeployService::class)
                        ->deploy($record->project, 'create', (string) $record->pr_number, $record->branch, $record->selected_branches ?? [], true)),

                Action::make('delete')
                    ->label('Supprimer sur Dokploy')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(fn (Staging $record) => app(DeployService::class)
                        ->deploy($record->project, 'delete', (string) $record->pr_number, $record->branch)),

                EditAction::make(),
            ])
            ->emptyStateHeading('Aucun staging pour ce projet')
            ->emptyStateDescription('Les environnements de prévisualisation apparaîtront ici.')
            ->defaultSort('created_at', 'desc');
    }
}
