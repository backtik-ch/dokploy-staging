<?php

namespace App\Filament\Resources\Dokploys\Pages;

use App\Filament\Resources\Dokploys\DokployResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDokploy extends EditRecord
{
    protected static string $resource = DokployResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
