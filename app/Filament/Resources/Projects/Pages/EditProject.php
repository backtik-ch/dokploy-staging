<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Pages\Concerns\HasProjectHeader;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    use HasProjectHeader;

    protected static string $resource = ProjectResource::class;

    protected static ?string $navigationLabel = 'Réglages';

    protected function getFormActions(): array
    {
        return [...parent::getFormActions(), DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $this->refreshProjectMaintenance();
    }
}
