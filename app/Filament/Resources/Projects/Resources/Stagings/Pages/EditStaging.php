<?php

namespace App\Filament\Resources\Projects\Resources\Stagings\Pages;

use App\Filament\Resources\Projects\Resources\Stagings\StagingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaging extends EditRecord
{
    protected static string $resource = StagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
