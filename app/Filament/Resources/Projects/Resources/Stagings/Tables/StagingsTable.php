<?php

namespace App\Filament\Resources\Projects\Resources\Stagings\Tables;

use App\Models\Staging;
use App\Services\DeployService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StagingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('pr_number'),
                TextColumn::make('branch'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('deploy')
                    ->color('success')
                    ->action(fn (Staging $record) => app(DeployService::class)
                        ->deploy($record->project, 'create', $record->pr_number, $record->branch)),

                Action::make('delete')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(fn (Staging $record) => app(DeployService::class)
                        ->deploy($record->project, 'delete', $record->pr_number, $record->branch)),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
