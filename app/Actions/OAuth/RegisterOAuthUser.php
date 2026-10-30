<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Data\OAuth\OAuthUserData;
use App\Models\User;
use App\Support\Registration;
use RuntimeException;

final readonly class RegisterOAuthUser
{
    public function __construct(
        private LinkOAuthAccount $linkOAuthAccount,
    ) {}

    /**
     * Provision a new user account originating from an OAuth provider.
     *
     * @throws RuntimeException
     */
    public function handle(OAuthUserData $userData): User
    {
        if (! Registration::open()) {
            throw new RuntimeException(message: 'Public registration is currently closed on this instance.');
        }

        $email = filled(value: $userData->email)
            ? (string) $userData->email
            : $userData->providerId.'@'.$userData->provider->value.'.vaulta.local';

        $name = filled(value: $userData->name)
            ? (string) $userData->name
            : (filled(value: $userData->nickname) ? (string) $userData->nickname : 'User');

        $isFirstUser = User::query()->count() === 0;

        $user = new User;
        $user->forceFill(attributes: [
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => null,
            'avatar' => $userData->avatar,
            'is_admin' => $isFirstUser,
            'palette' => 'ferrite',
            'is_blocked' => false,
        ]);
        $user->save();

        $this->linkOAuthAccount->handle(
            user: $user,
            userData: $userData,
        );

        return $user;
    }
}
