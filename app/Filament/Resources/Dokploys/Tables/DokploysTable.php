<?php

namespace App\Filament\Resources\Dokploys\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DokploysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Connexion')->searchable()->sortable()
                    ->placeholder(fn ($record): string => 'Connexion #'.$record->id),
                TextColumn::make('base_url')->label('URL Dokploy')->searchable()->copyable(),
                TextColumn::make('id')->label('Identifiant')->prefix('#')->sortable(),
                TextColumn::make('projects_count')->label('Projets')->counts('projects')->badge(),
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
