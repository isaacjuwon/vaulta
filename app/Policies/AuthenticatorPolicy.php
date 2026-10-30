<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Authenticator;
use App\Models\User;

final readonly class AuthenticatorPolicy
{
    public function view(User $user, Authenticator $authenticator): bool
    {
        return $user->id === $authenticator->user_id;
    }

    public function update(User $user, Authenticator $authenticator): bool
    {
        return $user->id === $authenticator->user_id;
    }

    public function delete(User $user, Authenticator $authenticator): bool
    {
        return $user->id === $authenticator->user_id;
    }
}
