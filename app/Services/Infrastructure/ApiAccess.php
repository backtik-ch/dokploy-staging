<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ApiAccess
{
    public const ABILITIES = [
        'infra:read', 'infra:write', 'stagings:deploy', 'stagings:delete',
        'maintenance:write', 'logs:read', 'tokens:manage', 'secrets:write',
        'dependencies:write',
    ];

    public static function allows(?User $user, string $ability): bool
    {
        return $user?->currentAccessToken() && $user->tokenCan($ability);
    }

    public static function authorize(?User $user, string $ability): void
    {
        if (! self::allows($user, $ability)) {
            throw new AuthorizationException('Permission requise : '.$ability.'.');
        }
    }
}
