<?php

namespace App\Filament\Resources\Users\Tables;

use App\Services\Infrastructure\UserTokenService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

class UserTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->select([
                'id', 'tokenable_type', 'tokenable_id', 'name', 'abilities',
                'expires_at', 'last_used_at', 'created_at', 'updated_at',
            ]))
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('abilities')->label('Droits')->badge()->wrap()
                    ->formatStateUsing(fn (string $state): string => $state === '*' ? 'Tous les droits' : $state),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('expires_at')->label('Expiration')->dateTime('d/m/Y H:i')->placeholder('Sans expiration')->sortable(),
                TextColumn::make('last_used_at')->label('Dernière utilisation')->dateTime('d/m/Y H:i')->placeholder('Jamais')->sortable(),
            ])
            ->emptyStateHeading('Aucun token')
            ->emptyStateDescription('Les tokens de cet utilisateur apparaîtront ici.')
            ->recordActions([
                Action::make('revoke')->label('Révoquer')->icon('heroicon-o-trash')->color('danger')
                    ->requiresConfirmation()->modalHeading('Révoquer ce token ?')
                    ->modalSubmitActionLabel('Révoquer')->modalCancelActionLabel('Annuler')
                    ->modalDescription('Les appels API utilisant ce token seront immédiatement refusés.')
                    ->authorize(fn (ManageRelatedRecords $livewire): bool => Gate::allows('revokeToken', $livewire->getRecord()))
                    ->action(function (PersonalAccessToken $record, ManageRelatedRecords $livewire, UserTokenService $tokens): void {
                        $tokens->revoke(auth()->user(), $livewire->getRecord(), $record->id);
                        Notification::make()->success()->title('Token révoqué')->send();
                    }),
            ]);
    }
}
