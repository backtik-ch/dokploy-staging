<?php

namespace App\Filament\Resources\Projects\Resources\Stagings\Pages;

use App\Filament\Actions\StagingDeletionActions;
use App\Filament\Resources\Projects\Resources\Stagings\StagingResource;
use Filament\Resources\Pages\EditRecord;

class EditStaging extends EditRecord
{
    protected static string $resource = StagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StagingDeletionActions::single(),
        ];
    }
}
