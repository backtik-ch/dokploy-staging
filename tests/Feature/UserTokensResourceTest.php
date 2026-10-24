<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ManageUserTokens;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Infrastructure\UserTokenService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    $this->actingAs($this->user);
    $this->ownToken = $this->user->createToken('Mon token', ['infra:read'], now()->addDays(30));
    $this->otherToken = $this->other->createToken('Token collègue', ['logs:read'], now()->addDays(7));
});

it('lists users and token counts in the sidebar resource without exposing token secrets', function (): void {
    expect(UserResource::shouldRegisterNavigation())->toBeTrue()
        ->and(UserResource::getPluralModelLabel())->toBe('Utilisateurs');
    $this->get(UserResource::getUrl('index'))->assertOk();
    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->user, $this->other])
        ->assertTableColumnStateSet('tokens_count', 1, $this->user)
        ->assertTableColumnStateSet('tokens_count', 1, $this->other)
        ->assertDontSee($this->ownToken->plainTextToken)
        ->assertDontSee($this->ownToken->accessToken->getRawOriginal('token'));
});

it('only lists the selected users token metadata without their values or hashes', function (): void {
    $this->get(UserResource::getUrl('tokens', ['record' => $this->other]))->assertOk();
    Livewire::test(ManageUserTokens::class, ['record' => $this->other->id])
        ->assertCanSeeTableRecords([$this->otherToken->accessToken])
        ->assertCanNotSeeTableRecords([$this->ownToken->accessToken])
        ->assertTableColumnStateSet('name', 'Token collègue', $this->otherToken->accessToken)
        ->assertTableColumnStateSet('abilities', ['logs:read'], $this->otherToken->accessToken)
        ->assertDontSee($this->otherToken->plainTextToken)
        ->assertDontSee($this->otherToken->accessToken->getRawOriginal('token'))
        ->assertDontSee($this->ownToken->plainTextToken);
});

it('opens the creation form without generating a token until it is submitted', function (): void {
    Livewire::test(ManageUserTokens::class, ['record' => $this->user->id])
        ->assertActionVisible('createToken')
        ->mountAction('createToken')
        ->assertActionMounted('createToken');

    $this->assertDatabaseCount('personal_access_tokens', 2);
});

it('creates a scoped token for the signed in user and only displays its value in the creation result', function (): void {
    $this->freezeTime();
    $component = Livewire::test(ManageUserTokens::class, ['record' => $this->user->id])
        ->callAction('createToken', ['name' => 'Mon skill', 'days' => 7, 'abilities' => ['infra:read', 'logs:read']])
        ->assertHasNoErrors()->assertActionMounted('generatedToken');

    $plainTextToken = $component->get('mountedActions.0.arguments.token');
    $token = PersonalAccessToken::findToken($plainTextToken);
    expect($token)->not->toBeNull()
        ->and($token->tokenable->is($this->user))->toBeTrue()
        ->and($token->name)->toBe('Mon skill')
        ->and($token->abilities)->toBe(['infra:read', 'logs:read'])
        ->and($token->expires_at->timestamp)->toBe(now()->addDays(7)->timestamp)
        ->and($this->other->tokens()->count())->toBe(1);
    $component->assertMountedActionModalSee($plainTextToken)->assertMountedActionModalSee('Copier le token')
        ->call('unmountAction')->assertSet('mountedActions', [])->assertDontSee($plainTextToken);
    Livewire::test(ManageUserTokens::class, ['record' => $this->user->id])
        ->assertCanSeeTableRecords([$token])->assertDontSee($plainTextToken)
        ->assertDontSee($token->getRawOriginal('token'));
});

it('hides creation for other users and rejects a forged action invocation', function (): void {
    Livewire::test(ManageUserTokens::class, ['record' => $this->other->id])
        ->assertActionHidden('createToken')
        ->call('mountAction', 'createToken')
        ->assertSet('mountedActions', []);

    $this->assertDatabaseCount('personal_access_tokens', 2);
});

it('enforces account ownership in the creation service independently of the button visibility', function (): void {
    try {
        app(UserTokenService::class)->create($this->user, $this->other, [
            'name' => 'Forbidden', 'days' => 7, 'abilities' => ['infra:read'],
        ]);
        $this->fail('Creating a token for another user must be forbidden.');
    } catch (AuthorizationException) {
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }
});

it('validates token permissions and validity before creating anything', function (array $data, array $errors): void {
    Livewire::test(ManageUserTokens::class, ['record' => $this->user->id])
        ->callAction('createToken', $data)->assertHasActionErrors($errors);

    $this->assertDatabaseCount('personal_access_tokens', 2);
})->with([
    'no permissions' => [['name' => 'Invalid', 'days' => 7, 'abilities' => []], ['abilities']],
    'unknown permission' => [['name' => 'Invalid', 'days' => 7, 'abilities' => ['unknown']], ['abilities.0']],
    'wildcard permission' => [['name' => 'Invalid', 'days' => 7, 'abilities' => ['*']], ['abilities.0']],
    'invalid duration' => [['name' => 'Invalid', 'days' => 366, 'abilities' => ['infra:read']], ['days']],
]);

it('revokes a token from the selected user without affecting anyone elses tokens', function (): void {
    Livewire::test(ManageUserTokens::class, ['record' => $this->other->id])
        ->callAction(TestAction::make('revoke')->table($this->otherToken->accessToken))
        ->assertNotified('Token révoqué');

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $this->otherToken->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $this->ownToken->accessToken->id]);
    expect(PersonalAccessToken::findToken($this->otherToken->plainTextToken))->toBeNull();
});

it('keeps revocation scoped to the selected users relationship', function (): void {
    try {
        app(UserTokenService::class)->revoke($this->user, $this->user, $this->otherToken->accessToken->id);
        $this->fail('A token from another account must not resolve under this account.');
    } catch (ModelNotFoundException) {
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }
});
