<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\OAuth\LinkOAuthAccount;
use App\Actions\OAuth\RegisterOAuthUser;
use App\Actions\OAuth\SynchronizeOAuthAccount;
use App\Data\OAuth\OAuthUserData;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class CreateOAuthUserData
{
    use Queueable;

    public function __construct(
        public OAuthUserData $userData,
        public ?int $connectingUserId = null,
    ) {}

    /**
     * Coordinate finding, creating, or linking user data from OAuth via dedicated actions.
     *
     * @throws RuntimeException
     */
    public function handle(
        ?LinkOAuthAccount $linkOAuthAccount = null,
        ?SynchronizeOAuthAccount $synchronizeOAuthAccount = null,
        ?RegisterOAuthUser $registerOAuthUser = null,
    ): User {
        $linkOAuthAccount ??= app(abstract: LinkOAuthAccount::class);
        $synchronizeOAuthAccount ??= app(abstract: SynchronizeOAuthAccount::class);
        $registerOAuthUser ??= app(abstract: RegisterOAuthUser::class);

        // Case 1: Connect account to existing authenticated user
        if ($this->connectingUserId !== null) {
            /** @var User $currentUser */
            $currentUser = User::query()->findOrFail(id: $this->connectingUserId);

            $linkOAuthAccount->handle(
                user: $currentUser,
                userData: $this->userData,
            );

            return $currentUser;
        }

        // Case 2: Returning user signing in with an existing OAuth account
        /** @var OAuthAccount|null $existingAccount */
        $existingAccount = OAuthAccount::query()
            ->with(relations: ['user'])
            ->where(column: 'provider', operator: '=', value: $this->userData->provider)
            ->where(column: 'provider_id', operator: '=', value: $this->userData->providerId)
            ->first();

        if ($existingAccount !== null) {
            return $synchronizeOAuthAccount->handle(
                account: $existingAccount,
                userData: $this->userData,
            );
        }

        // Case 3: Match existing user by verified provider email
        if (filled(value: $this->userData->email)) {
            /** @var User|null $existingUserByEmail */
            $existingUserByEmail = User::query()
                ->where(column: 'email', operator: '=', value: $this->userData->email)
                ->first();

            if ($existingUserByEmail !== null) {
                $linkOAuthAccount->handle(
                    user: $existingUserByEmail,
                    userData: $this->userData,
                    markEmailVerified: true,
                );

                return $existingUserByEmail;
            }
        }

        // Case 4: Provision new user
        return $registerOAuthUser->handle(userData: $this->userData);
    }
}
