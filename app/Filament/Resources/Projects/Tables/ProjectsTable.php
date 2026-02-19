<?php

namespace App\Filament\Resources\Projects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dokploy.base_url'),
                TextColumn::make('app_name'),
                TextColumn::make('dokploy_project_id'),
                TextColumn::make('server_id'),
                TextColumn::make('github_id'),
                TextColumn::make('github_owner'),
                TextColumn::make('github_repository'),
                TextColumn::make('compose_name_file'),
                TextColumn::make('domain_name'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
