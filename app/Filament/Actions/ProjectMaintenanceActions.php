<?php

namespace App\Filament\Actions;

use App\Exceptions\MaintenanceException;
use App\Models\Project;
use App\Services\MaintenanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ProjectMaintenanceActions
{
    public static function refresh(): Action
    {
        return self::make('refreshMaintenance', 'Actualiser le statut', null, 'gray', 'heroicon-o-arrow-path');
    }

    public static function toggle(): Action
    {
        return Action::make('toggleMaintenance')
            ->label(fn (Project $record): string => match ($record->maintenance_enabled) {
                true => 'Remettre en ligne',
                false => 'Mettre en maintenance',
                null => 'Statut de maintenance indisponible',
            })
            ->icon(fn (Project $record): string => $record->maintenance_enabled ? 'heroicon-o-play' : 'heroicon-o-wrench-screwdriver')
            ->color(fn (Project $record): string => $record->maintenance_enabled ? 'success' : 'warning')
            ->visible(fn (Project $record): bool => filled($record->maintenance_yml_file))
            ->disabled(fn (Project $record): bool => $record->maintenance_enabled === null || filled($record->maintenance_error))
            ->tooltip(fn (Project $record): ?string => $record->maintenance_error)
            ->authorize('update')
            ->action(fn (Project $record) => self::execute($record, ! $record->maintenance_enabled));
    }

    public static function all(): array
    {
        return [
            self::make('enableMaintenance', 'Mettre en maintenance', true, 'warning', 'heroicon-o-wrench-screwdriver'),
            self::make('disableMaintenance', 'Remettre en ligne', false, 'success', 'heroicon-o-play'),
            self::refresh(),
        ];
    }

    private static function make(string $name, string $label, ?bool $enabled, string $color, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->visible(fn (Project $record): bool => filled($record->maintenance_yml_file))
            ->authorize('update')
            ->action(fn (Project $record) => self::execute($record, $enabled));
    }

    private static function execute(Project $record, ?bool $enabled): void
    {
        try {
            $service = app(MaintenanceService::class);
            $actual = $enabled === null ? $service->refresh($record) : $service->setEnabled($record, $enabled);

            Notification::make()->success()
                ->title($actual ? 'Maintenance activée et vérifiée' : 'Maintenance désactivée et vérifiée')
                ->body('Le fichier Traefik a été relu sur Dokploy.')
                ->send();
        } catch (MaintenanceException $exception) {
            Notification::make()->danger()->title('Maintenance non confirmée')->body($exception->getMessage())->persistent()->send();
        }
    }
}
