<?php

use App\Models\User;
use App\Services\Infrastructure\ApiAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('asks every permission and creates a token for the selected account with only the accepted rights', function (): void {
    $this->freezeTime();
    $other = User::factory()->create();
    $user = User::factory()->create();
    $allowed = ['infra:read', 'logs:read'];
    $command = $this->artisan('make:api-token')
        ->expectsQuestion('Pour quel compte ?', (string) $user->id)
        ->expectsQuestion('Nom du token', 'Mon skill')
        ->expectsQuestion('Durée de validité (en jours)', '7');

    foreach (ApiAccess::ABILITIES as $ability) {
        $command->expectsConfirmation('Autoriser '.$ability.' ?', in_array($ability, $allowed, true) ? 'yes' : 'no');
    }
    $command->expectsOutput('Token créé. Copiez sa valeur maintenant : elle ne sera plus affichée.')->assertSuccessful()->run();

    $token = $user->tokens()->sole();
    expect($token->name)->toBe('Mon skill')->and($token->abilities)->toBe($allowed)
        ->and($token->expires_at->timestamp)->toBe(now()->addDays(7)->timestamp)
        ->and($other->tokens()->count())->toBe(0);
});

it('does not silently grant read access when only a different permission was selected', function (): void {
    $user = User::factory()->create();
    $command = $this->artisan('make:api-token')
        ->expectsQuestion('Pour quel compte ?', (string) $user->id)
        ->expectsQuestion('Nom du token', 'CI dependencies')
        ->expectsQuestion('Durée de validité (en jours)', '30');

    foreach (ApiAccess::ABILITIES as $ability) {
        $command->expectsConfirmation('Autoriser '.$ability.' ?', $ability === 'dependencies:write' ? 'yes' : 'no');
    }
    $command->assertSuccessful()->run();

    expect($user->tokens()->sole()->abilities)->toBe(['dependencies:write']);
});

it('does not create a token when every permission is refused', function (): void {
    $user = User::factory()->create();
    $command = $this->artisan('make:api-token')
        ->expectsQuestion('Pour quel compte ?', (string) $user->id)
        ->expectsQuestion('Nom du token', 'No permissions')
        ->expectsQuestion('Durée de validité (en jours)', '30');

    foreach (ApiAccess::ABILITIES as $ability) {
        $command->expectsConfirmation('Autoriser '.$ability.' ?', 'no');
    }
    $command->expectsOutput('Aucun droit sélectionné. Aucun token n’a été créé.')->assertFailed()->run();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('requires an existing account before asking any questions', function (): void {
    $this->artisan('make:api-token')
        ->expectsOutput('Aucun compte existant. Créez un utilisateur avant de générer un token.')
        ->assertFailed();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('does not create a token in non interactive mode', function (): void {
    User::factory()->create();
    $this->artisan('make:api-token', ['--no-interaction' => true])
        ->expectsOutput('Cette commande est interactive. Utilisez infra:token pour une exécution sans interaction.')
        ->assertFailed();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects invalid validity periods before asking for permissions', function (string $days): void {
    $user = User::factory()->create();
    $this->artisan('make:api-token')
        ->expectsQuestion('Pour quel compte ?', (string) $user->id)
        ->expectsQuestion('Nom du token', 'Invalid duration')
        ->expectsQuestion('Durée de validité (en jours)', $days)
        ->assertFailed();

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with(['0', '366', 'abc']);
