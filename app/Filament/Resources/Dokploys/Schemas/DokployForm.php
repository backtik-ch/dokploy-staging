<?php

namespace App\Filament\Resources\Dokploys\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DokployForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('base_url'),
                TextInput::make('token')
            ]);
    }
}
