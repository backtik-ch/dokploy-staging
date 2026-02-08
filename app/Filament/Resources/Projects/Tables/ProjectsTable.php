<?php

namespace App\Filament\Resources\Projects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tiptap\Nodes\Text;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dokploy.base_url'),
                TextColumn::make('dokploy.app_name'),
                TextColumn::make('dokploy.dokploy_project_id'),
                TextColumn::make('dokploy.github_id'),
                TextColumn::make('dokploy.github_owner'),
                TextColumn::make('dokploy.github_repository'),
                TextColumn::make('dokploy.compose_name_file'),
                TextColumn::make('dokploy.domain_name'),
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
