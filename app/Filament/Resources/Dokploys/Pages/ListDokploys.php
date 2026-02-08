<?php

namespace App\Filament\Resources\Dokploys\Pages;

use App\Filament\Resources\Dokploys\DokployResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDokploys extends ListRecords
{
    protected static string $resource = DokployResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
