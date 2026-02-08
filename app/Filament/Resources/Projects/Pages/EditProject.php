<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Services\DeployService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deploy')
                ->schema([
                    TextInput::make('pr_number'),
                    TextInput::make('branch'),
                ])
            ->action(function($record, $data) {
                app(DeployService::class)
                    ->deploy($record, 'create', $data['pr_number'], $data['branch']);
            }),
            DeleteAction::make(),
        ];
    }
}
