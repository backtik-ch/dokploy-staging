<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\ApiTokenForm;
use App\Filament\Resources\Users\Tables\UserTokensTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Infrastructure\UserTokenService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ManageUserTokens extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'tokens';

    protected static ?string $relationshipTitle = 'tokens';

    public static function canAccess(array $parameters = []): bool
    {
        $user = $parameters['record'] ?? null;

        return $user instanceof User && Gate::allows('view', $user);
    }

    public function table(Table $table): Table
    {
        return UserTokensTable::configure(parent::table($table))
            ->heading('Tokens de '.$this->getRecord()->name)
            ->description('Noms, droits et dates uniquement. Les valeurs des tokens ne sont pas conservées en clair.');
    }

    public function getTitle(): string
    {
        return 'Tokens de '.$this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->email;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')->label('Créer un token')->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()->is($this->getRecord()))
                ->authorize(fn (): bool => Gate::allows('createToken', $this->getRecord()))
                ->modalHeading('Créer un token pour mon compte')->modalWidth('3xl')
                ->modalSubmitActionLabel('Créer le token')->modalCancelActionLabel('Annuler')
                ->schema(ApiTokenForm::components())
                ->action(function (array $data, UserTokenService $tokens): void {
                    $token = $tokens->create(auth()->user(), $this->getRecord(), $data);
                    $this->replaceMountedAction('generatedToken', ['token' => $token->plainTextToken]);
                }),
        ];
    }

    public function generatedTokenAction(): Action
    {
        return Action::make('generatedToken')->modalHeading('Token créé')
            ->authorize(fn (): bool => Gate::allows('createToken', $this->getRecord()))
            ->modalContent(fn (array $arguments): View => view('filament.modals.generated-token', ['token' => $arguments['token'] ?? '']))
            ->modalSubmitAction(false)->modalCancelActionLabel('Fermer');
    }
}
