<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('server_id'),
                TextInput::make('github_id')
                    ->required(),
                TextInput::make('github_owner')
                    ->required(),
                TextInput::make('github_repository')
                    ->required(),

                TextInput::make('github_token')
                    ->password()
                    ->revealable()
                    ->helperText('Token GitHub (scope lecture des repositories/branches). Si vide, utilise SERVICES_GITHUB_TOKEN.'),

                Toggle::make('wait_for_main_image')
                    ->label('Attendre l’image de la branche principale')
                    ->default(true)
                    ->afterStateHydrated(fn (Toggle $component, ?bool $state) => $component->state($state ?? true))
                    ->helperText('Si activé, le déploiement attend aussi l’image GHCR du repo principal.'),

                Repeater::make('linked_repositories')
                    ->label('Repositories liés')
                    ->schema([
                        TextInput::make('label')
                            ->required()
                            ->helperText('Nom affiché dans le formulaire de staging (ex: frontend, backend, admin).'),
                        TextInput::make('owner')
                            ->required(),
                        TextInput::make('repository')
                            ->required(),
                        TextInput::make('branch_placeholder')
                            ->required()
                            ->helperText('Placeholder dans environment_staging à remplacer, ex: FRONTEND_BRANCH pour {FRONTEND_BRANCH}.'),
                        Toggle::make('wait_for_image')
                            ->label('Attendre l’image')
                            ->default(true)
                            ->afterStateHydrated(fn (Toggle $component, ?bool $state) => $component->state($state ?? true))
                            ->helperText('Si désactivé, cette branche est injectée dans l’environnement mais son image GHCR n’est pas attendue.'),
                    ])
                    ->columns(2)
                    ->default([])
                    ->collapsible(),

                TextInput::make('compose_name_file')
                    ->required(),
                TextInput::make('domain_name')
                    ->required(),

                TextInput::make('service_name')
                    ->default('server'),

                TagsInput::make('extra_sub_domains'),

                Textarea::make('environment_staging'),
            ]);
    }
}
