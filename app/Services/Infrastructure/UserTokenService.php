<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\NewAccessToken;

class UserTokenService
{
    public function create(User $actor, User $owner, array $data): NewAccessToken
    {
        Gate::forUser($actor)->authorize('createToken', $owner);

        return $owner->createToken($data['name'], $data['abilities'], now()->addDays($data['days']));
    }

    public function revoke(User $actor, User $owner, int $tokenId): void
    {
        Gate::forUser($actor)->authorize('revokeToken', $owner);
        $owner->tokens()->findOrFail($tokenId)->delete();
    }
}
