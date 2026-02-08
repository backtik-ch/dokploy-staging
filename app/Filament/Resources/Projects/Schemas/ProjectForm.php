<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dokploy_id')
                    ->relationship('dokploy', 'base_url')
                    ->required(),

                TextInput::make('app_name')
                    ->required(),

                TextInput::make('dokploy_project_id')
                    ->required(),
                TextInput::make('github_id')
                    ->required(),
                TextInput::make('github_owner')
                    ->required(),
                TextInput::make('github_repository')
                    ->required(),
                TextInput::make('compose_name_file')
                    ->required(),
                TextInput::make('domain_name')
                    ->required(),

                Textarea::make('environment_staging')
            ]);
    }
}
