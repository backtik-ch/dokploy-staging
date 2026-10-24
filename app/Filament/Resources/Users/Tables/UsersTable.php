<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordUrl(fn (User $record): string => UserResource::getUrl('tokens', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->sortable(),
                TextColumn::make('tokens_count')->label('Tokens')->counts('tokens')->badge()->sortable(),
            ])
            ->recordActions([
                Action::make('tokens')->label('Tokens')->icon('heroicon-o-key')
                    ->url(fn (User $record): string => UserResource::getUrl('tokens', ['record' => $record])),
            ]);
    }
}
