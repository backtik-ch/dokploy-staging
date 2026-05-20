<?php

namespace App\Filament\Resources\Projects\Resources\Stagings\Schemas;

use App\Models\Project;
use App\Services\GithubService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StagingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('staging_reference')
                    ->label('Référence staging')
                    ->required()
                    ->maxLength(255),

                Select::make('branch')
                    ->label('Branche principale')
                    ->required()
                    ->default('main')
                    ->disabled()
                    ->dehydrated()
                    ->searchable()
                    ->options(function ($livewire): array {
                        $project = self::resolveProject($livewire);

                        if (! $project) {
                            return [];
                        }

                        return app(GithubService::class)->listBranches(
                            $project,
                            $project->github_owner,
                            $project->github_repository,
                        );
                    }),

                \Filament\Schemas\Components\Section::make('Branches des repositories liés')
                    ->schema(function ($livewire): array {
                        $project = self::resolveProject($livewire);

                        if (! $project) {
                            return [];
                        }

                        return collect($project->linked_repositories ?? [])
                            ->filter(fn (array $repo) => filled($repo['owner'] ?? null) && filled($repo['repository'] ?? null) && filled($repo['branch_placeholder'] ?? null))
                            ->map(function (array $repo) use ($project) {
                                $label = $repo['label'] ?? (($repo['owner'] ?? '').'/'.($repo['repository'] ?? ''));

                                return Select::make('selected_branches.'.$repo['branch_placeholder'])
                                    ->label($label)
                                    ->required()
                                    ->searchable()
                                    ->options(fn (): array => app(GithubService::class)->listBranches(
                                        $project,
                                        $repo['owner'],
                                        $repo['repository'],
                                    ));
                            })
                            ->values()
                            ->all();
                    })
                    ->columns(2),
            ]);
    }

    private static function resolveProject(mixed $livewire): ?Project
    {
        if (method_exists($livewire, 'getOwnerRecord')) {
            return $livewire->getOwnerRecord();
        }

        if (method_exists($livewire, 'getRecord') && $livewire->getRecord() instanceof Project) {
            return $livewire->getRecord();
        }

        if (property_exists($livewire, 'ownerRecord') && $livewire->ownerRecord instanceof Project) {
            return $livewire->ownerRecord;
        }

        return null;
    }
}
