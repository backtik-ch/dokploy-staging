<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Services\Infrastructure\ApiAccess;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;

class ApiTokenForm
{
    public static function components(): array
    {
        return [
            TextInput::make('name')->label('Nom du token')->required()->maxLength(255)->default('api-token'),
            TextInput::make('days')->label('Validité (jours)')->integer()->minValue(1)->maxValue(365)->required()->default(30),
            CheckboxList::make('abilities')->label('Droits')
                ->options(array_combine(ApiAccess::ABILITIES, ApiAccess::ABILITIES))
                ->descriptions(ApiAccess::ABILITY_DESCRIPTIONS)
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->required()->rules(['array', 'min:1'])->default([]),
        ];
    }
}
