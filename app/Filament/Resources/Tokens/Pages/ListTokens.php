<?php

namespace App\Filament\Resources\Tokens\Pages;

use App\Filament\Resources\Tokens\TokenResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->action(function () {
                    $token = auth()->user()->createToken('auto_'.now()->timestamp);

                    dd($token);
                }),
        ];
    }
}
