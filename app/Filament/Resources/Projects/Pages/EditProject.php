<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Services\DeployService;
use App\Services\GithubService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deploy')
                ->schema(function ($record): array {
                    $components = [
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
                            ->options(fn (): array => app(GithubService::class)->listBranches(
                                $record,
                                $record->github_owner,
                                $record->github_repository,
                            )),
                    ];

                    $linkedRepoFields = collect($record->linked_repositories ?? [])
                        ->filter(fn (array $repo) => filled($repo['owner'] ?? null) && filled($repo['repository'] ?? null) && filled($repo['branch_placeholder'] ?? null))
                        ->map(function (array $repo) use ($record) {
                            $label = $repo['label'] ?? (($repo['owner'] ?? '').'/'.($repo['repository'] ?? ''));

                            return Select::make('selected_branches.'.$repo['branch_placeholder'])
                                ->label($label)
                                ->required()
                                ->searchable()
                                ->options(fn (): array => app(GithubService::class)->listBranches(
                                    $record,
                                    $repo['owner'],
                                    $repo['repository'],
                                ));
                        })
                        ->values()
                        ->all();

                    if (! empty($linkedRepoFields)) {
                        $components[] = Section::make('Branches des repositories liés')
                            ->schema($linkedRepoFields)
                            ->columns(2);
                    }

                    return $components;
                })
                ->action(function ($record, $data) {
                    app(DeployService::class)
                        ->deploy($record, 'create', (string) $data['staging_reference'], (string) $data['branch'], $data['selected_branches'] ?? []);
                }),
            DeleteAction::make(),
        ];
    }
}
