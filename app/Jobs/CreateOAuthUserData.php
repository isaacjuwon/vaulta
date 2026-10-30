<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\OAuth\OAuthUserData;
use App\Models\OAuthAccount;
use App\Models\User;
use App\Support\Registration;
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
     * Execute the job to find, create, or link user data from OAuth.
     *
     * @throws RuntimeException
     */
    public function handle(): User
    {
        // Case 1: Connect account to existing authenticated user
        if ($this->connectingUserId !== null) {
            /** @var User $currentUser */
            $currentUser = User::query()->findOrFail(id: $this->connectingUserId);

            $existingLink = OAuthAccount::query()
                ->where(column: 'provider', operator: '=', value: $this->userData->provider)
                ->where(column: 'provider_id', operator: '=', value: $this->userData->providerId)
                ->first();

            if ($existingLink !== null && $existingLink->user_id !== $currentUser->id) {
                throw new RuntimeException(message: 'This social account is already linked to a different Vaulta user.');
            }

            OAuthAccount::query()->updateOrCreate(
                attributes: [
                    'provider' => $this->userData->provider,
                    'provider_id' => $this->userData->providerId,
                ],
                values: [
                    'user_id' => $currentUser->id,
                    'provider_email' => $this->userData->email,
                    'name' => $this->userData->name,
                    'nickname' => $this->userData->nickname,
                    'avatar' => $this->userData->avatar,
                    'access_token' => $this->userData->accessToken,
                    'refresh_token' => $this->userData->refreshToken,
                    'token_expires_at' => $this->userData->expiresAt,
                ],
            );

            if ($currentUser->avatar === null && $this->userData->avatar !== null) {
                $currentUser->forceFill(attributes: ['avatar' => $this->userData->avatar])->save();
            }

            return $currentUser;
        }

        // Case 2: Guest user signing in or registering via OAuth
        /** @var OAuthAccount|null $existingAccount */
        $existingAccount = OAuthAccount::query()
            ->with(relations: ['user'])
            ->where(column: 'provider', operator: '=', value: $this->userData->provider)
            ->where(column: 'provider_id', operator: '=', value: $this->userData->providerId)
            ->first();

        if ($existingAccount !== null) {
            $existingAccount->update(attributes: [
                'provider_email' => $this->userData->email,
                'name' => $this->userData->name,
                'nickname' => $this->userData->nickname,
                'avatar' => $this->userData->avatar,
                'access_token' => $this->userData->accessToken,
                'refresh_token' => $this->userData->refreshToken,
                'token_expires_at' => $this->userData->expiresAt,
            ]);

            return $existingAccount->user;
        }

        // Case 3: Check by email match if provider provided an email
        if (filled(value: $this->userData->email)) {
            /** @var User|null $existingUserByEmail */
            $existingUserByEmail = User::query()
                ->where(column: 'email', operator: '=', value: $this->userData->email)
                ->first();

            if ($existingUserByEmail !== null) {
                OAuthAccount::query()->create(attributes: [
                    'user_id' => $existingUserByEmail->id,
                    'provider' => $this->userData->provider,
                    'provider_id' => $this->userData->providerId,
                    'provider_email' => $this->userData->email,
                    'name' => $this->userData->name,
                    'nickname' => $this->userData->nickname,
                    'avatar' => $this->userData->avatar,
                    'access_token' => $this->userData->accessToken,
                    'refresh_token' => $this->userData->refreshToken,
                    'token_expires_at' => $this->userData->expiresAt,
                ]);

                if ($existingUserByEmail->email_verified_at === null) {
                    $existingUserByEmail->forceFill(attributes: ['email_verified_at' => now()])->save();
                }

                return $existingUserByEmail;
            }
        }

        // Case 4: Create new user (Sign-up)
        if (! Registration::open()) {
            throw new RuntimeException(message: 'Public registration is currently closed on this instance.');
        }

        $email = filled(value: $this->userData->email)
            ? (string) $this->userData->email
            : $this->userData->providerId.'@'.$this->userData->provider->value.'.vaulta.local';

        $name = filled(value: $this->userData->name)
            ? (string) $this->userData->name
            : (filled(value: $this->userData->nickname) ? (string) $this->userData->nickname : 'User');

        $isFirstUser = User::query()->count() === 0;

        $user = new User;
        $user->forceFill(attributes: [
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => null,
            'avatar' => $this->userData->avatar,
            'is_admin' => $isFirstUser,
            'palette' => 'ferrite',
            'is_blocked' => false,
        ]);
        $user->save();

        OAuthAccount::query()->create(attributes: [
            'user_id' => $user->id,
            'provider' => $this->userData->provider,
            'provider_id' => $this->userData->providerId,
            'provider_email' => $this->userData->email,
            'name' => $this->userData->name,
            'nickname' => $this->userData->nickname,
            'avatar' => $this->userData->avatar,
            'access_token' => $this->userData->accessToken,
            'refresh_token' => $this->userData->refreshToken,
            'token_expires_at' => $this->userData->expiresAt,
        ]);

        return $user;
    }
}
