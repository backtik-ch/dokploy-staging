<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $owner): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $owner): bool
    {
        return false;
    }

    public function delete(User $user, User $owner): bool
    {
        return false;
    }

    public function createToken(User $user, User $owner): bool
    {
        return $user->is($owner);
    }

    public function revokeToken(User $user, User $owner): bool
    {
        return true;
    }
}
