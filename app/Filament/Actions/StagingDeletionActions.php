<?php

namespace App\Filament\Actions;

use App\Exceptions\StagingDeletionException;
use App\Models\Staging;
use App\Services\StagingDeletionService;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

class StagingDeletionActions
{
    public static function single(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Supprimer')
            ->modalDescription('Le staging sera supprimé sur Dokploy puis en local. S’il est déjà absent de Dokploy, la suppression locale sera effectuée.')
            ->successNotificationTitle('Staging supprimé')
            ->failureNotificationTitle('Suppression impossible')
            ->using(function (DeleteAction $action, Staging $record): bool {
                try {
                    return app(StagingDeletionService::class)->delete($record);
                } catch (StagingDeletionException $exception) {
                    $action->failureNotificationBody($exception->getMessage());

                    return false;
                }
            });
    }

    public static function bulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label('Supprimer')
            ->authorizeIndividualRecords('delete')
            ->modalDescription('Les stagings sélectionnés seront supprimés sur Dokploy puis en local. Ceux déjà absents de Dokploy seront supprimés uniquement en local.')
            ->successNotificationTitle('Stagings supprimés')
            ->using(function (DeleteBulkAction $action, Collection|LazyCollection $records): void {
                $service = app(StagingDeletionService::class);

                foreach ($records as $record) {
                    try {
                        $service->delete($record) || $action->reportBulkProcessingFailure();
                    } catch (Throwable $exception) {
                        if (! $exception instanceof StagingDeletionException) {
                            report($exception);
                        }

                        $message = $exception instanceof StagingDeletionException
                            ? $exception->getMessage()
                            : 'Une erreur inattendue a empêché la suppression.';

                        $action->reportBulkProcessingFailure((string) $record->getKey(), e('Staging '.$record->pr_number.' : '.$message));
                    }
                }
            });
    }
}
