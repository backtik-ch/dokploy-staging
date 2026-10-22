<?php

namespace App\Filament\Resources\Projects\Pages\Concerns;

use App\Exceptions\MaintenanceException;
use App\Filament\Actions\CreateProjectStagingAction;
use App\Filament\Actions\ProjectMaintenanceActions;
use App\Services\MaintenanceService;
use Filament\Notifications\Notification;

trait HasProjectHeader
{
    public function mountHasProjectHeader(): void
    {
        $this->refreshProjectMaintenance();
    }

    public function getTitle(): string
    {
        return $this->getRecord()->app_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            ProjectMaintenanceActions::toggle(),
            CreateProjectStagingAction::make(),
        ];
    }

    protected function refreshProjectMaintenance(): void
    {
        if (blank($this->getRecord()->maintenance_yml_file)) {
            return;
        }

        try {
            app(MaintenanceService::class)->refreshCached($this->getRecord());
        } catch (MaintenanceException $exception) {
            Notification::make()->danger()->title('Statut de maintenance indisponible')
                ->body($exception->getMessage())->send();
        }
    }
}
