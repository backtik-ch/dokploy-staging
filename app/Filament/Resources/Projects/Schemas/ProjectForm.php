<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Dokploy;
use App\Rules\MaintenanceFile;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Réglages du projet')->columnSpanFull()->tabs([
                    Tab::make('Projet')->icon('heroicon-o-square-3-stack-3d')->schema([
                        TextInput::make('app_name')->label('Nom du projet')->required()->maxLength(255),
                        Select::make('dokploy_id')
                            ->label('Connexion Dokploy')
                            ->relationship('dokploy', 'base_url')
                            ->getOptionLabelFromRecordUsing(fn (Dokploy $record): string => $record->connectionLabel())
                            ->searchable(['name', 'base_url'])
                            ->preload()
                            ->required()
                            ->helperText('Chaque connexion identifie une instance et un token. Gérez leurs noms dans Connexions Dokploy.'),
                        TextInput::make('server_id')->label('ID du serveur Dokploy')
                            ->maxLength(255)->helperText('Laissez vide pour le serveur local. Utilisé par la maintenance et les stagings.'),
                    ])->columns(2),
                    Tab::make('Maintenance')->icon('heroicon-o-wrench-screwdriver')->schema([
                        TextInput::make('maintenance_yml_file')
                            ->label('Fichier YAML de maintenance')
                            ->placeholder('mon-site-maintenance.yml')
                            ->maxLength(255)
                            ->rules([new MaintenanceFile])
                            ->helperText('Nom relatif à /etc/dokploy/traefik/dynamic ou chemin absolu sur le serveur. Le fichier doit déjà contenir un unique drapeau {{ $maintenance_enabled := 0 }} (ou 1). La connexion et le serveur du projet sont réutilisés.'),
                    ]),
                    Tab::make('Stagings')->icon('heroicon-o-beaker')->schema([
                        Section::make('Configuration des stagings')
                            ->description('À renseigner avant le premier déploiement de staging.')
                            ->schema([
                                TextInput::make('dokploy_project_id')->label('ID du projet Dokploy')->maxLength(255),
                                TextInput::make('github_id')->label('ID de l’intégration GitHub Dokploy')->maxLength(255),
                                TextInput::make('github_owner')->label('Organisation / propriétaire GitHub')->maxLength(255),
                                TextInput::make('github_repository')->label('Repository principal')->maxLength(255),

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

                                TextInput::make('compose_name_file')->label('Fichier Docker Compose')->maxLength(255),
                                TextInput::make('domain_name')->label('Domaine des stagings')->maxLength(255),

                                Select::make('certificate_type')
                                    ->label('Certificat TLS')
                                    ->options([
                                        'letsencrypt' => "Let's Encrypt",
                                        'custom' => 'Custom',
                                    ])
                                    ->default('letsencrypt')
                                    ->required()
                                    ->live()
                                    ->helperText('Utilisé pour les nouveaux domaines de staging.'),

                                TextInput::make('custom_cert_resolver')
                                    ->label('Résolveur de certificat custom')
                                    ->default('infomaniak')
                                    ->required()
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('certificate_type') === 'custom')
                                    ->helperText('Nom du résolveur configuré dans Dokploy.'),

                                TextInput::make('service_name')->label('Service Docker Compose')
                                    ->default('server'),

                                TextInput::make('service_port')
                                    ->label('Port du service')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(65535)
                                    ->default(80)
                                    ->required()
                                    ->helperText('Port interne du service ciblé par les domaines de staging (ex: 80 ou 8080).'),

                                TagsInput::make('extra_sub_domains')->label('Sous-domaines supplémentaires'),

                                Textarea::make('environment_staging')->label('Variables d’environnement des stagings')->rows(8)->columnSpanFull(),
                            ])->columns(2),
                    ]),
                ]),
            ]);
    }
}
