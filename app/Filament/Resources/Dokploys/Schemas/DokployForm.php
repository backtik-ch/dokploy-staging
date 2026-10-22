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
                TextInput::make('name')->label('Nom de la connexion')->required()->maxLength(255)
                    ->placeholder('Production · token équipe Backtik')
                    ->helperText('Un nom distinct pour chaque instance / token, y compris si plusieurs connexions utilisent la même URL.'),
                TextInput::make('base_url')->label('URL Dokploy')->url()->required()->maxLength(255)
                    ->placeholder('https://dokploy.example.com'),
                TextInput::make('token')->label('Token API Dokploy')->password()->revealable()->required()
                    ->helperText('Pour la maintenance, le token doit pouvoir lire et modifier les fichiers Traefik.'),
            ]);
    }
}
