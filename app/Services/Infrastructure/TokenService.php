<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        ApiAccess::authorize($user, 'tokens:manage');

        return $user->tokens()->orderBy('id')
            ->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
    }

    public function find(int $id, User $user): PersonalAccessToken
    {
        ApiAccess::authorize($user, 'tokens:manage');

        return $user->tokens()->findOrFail($id);
    }

    public function create(array $data, User $user): NewAccessToken
    {
        ApiAccess::authorize($user, 'tokens:manage');
        $abilities = $data['abilities'] ?? ['infra:read'];
        $expiresAt = $this->creationExpiry($data, $user);
        $this->authorizeGrant($user, $abilities, $expiresAt);

        return $user->createToken($data['name'], $abilities, $expiresAt);
    }

    public function update(int $id, array $data, User $user): PersonalAccessToken
    {
        $token = $this->find($id, $user);
        $expiresAt = isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : $token->expires_at?->toImmutable();
        $this->authorizeGrant($user, $data['abilities'] ?? $token->abilities, $expiresAt);
        $token->update($data);

        return $token->refresh();
    }

    public function rotate(int $id, User $user): NewAccessToken
    {
        ApiAccess::authorize($user, 'tokens:manage');

        return DB::transaction(function () use ($id, $user): NewAccessToken {
            $token = $user->tokens()->lockForUpdate()->findOrFail($id);
            $expiresAt = $token->expires_at?->toImmutable();
            $this->authorizeGrant($user, $token->abilities, $expiresAt);
            $replacement = $user->createToken($token->name, $token->abilities, $expiresAt);
            $token->delete();

            return $replacement;
        });
    }

    public function delete(int $id, User $user): PersonalAccessToken
    {
        $token = $this->find($id, $user);
        $token->delete();

        return $token;
    }

    private function creationExpiry(array $data, User $user): CarbonImmutable
    {
        if (isset($data['expires_at'])) {
            return CarbonImmutable::parse($data['expires_at']);
        }
        $default = CarbonImmutable::now()->addDays(30);
        $callerExpiry = $this->callerExpiresAt($user);

        return $callerExpiry && $callerExpiry->lessThan($default) ? $callerExpiry : $default;
    }

    private function authorizeGrant(User $user, array $abilities, ?CarbonImmutable $expiresAt): void
    {
        foreach ($abilities as $ability) {
            ApiAccess::authorize($user, $ability);
        }
        $callerExpiry = $this->callerExpiresAt($user);
        if ($callerExpiry && (! $expiresAt || $expiresAt->greaterThan($callerExpiry))) {
            throw ValidationException::withMessages(['expires_at' => 'Un token délégué ne peut pas dépasser l’expiration du token appelant.']);
        }
        if ($expiresAt?->isPast()) {
            throw ValidationException::withMessages(['expires_at' => 'La date d’expiration doit être dans le futur.']);
        }
    }

    private function callerExpiresAt(User $user): ?CarbonImmutable
    {
        $token = $user->currentAccessToken();
        $expiresAt = $token->expires_at?->toImmutable();
        if (config('sanctum.expiration')) {
            $globalExpiry = $token->created_at->toImmutable()->addMinutes((int) config('sanctum.expiration'));
            if (! $expiresAt || $globalExpiry->lessThan($expiresAt)) {
                $expiresAt = $globalExpiry;
            }
        }

        return $expiresAt;
    }
}
