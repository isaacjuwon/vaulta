<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Data\OAuth\OAuthUserData;
use App\Models\OAuthAccount;
use App\Models\User;
use RuntimeException;

final readonly class LinkOAuthAccount
{
    /**
     * Link or update an OAuth account identity for a given user.
     *
     * @throws RuntimeException
     */
    public function handle(User $user, OAuthUserData $userData, bool $markEmailVerified = false): OAuthAccount
    {
        $existingLink = OAuthAccount::query()
            ->where(column: 'provider', operator: '=', value: $userData->provider)
            ->where(column: 'provider_id', operator: '=', value: $userData->providerId)
            ->first();

        if ($existingLink !== null && $existingLink->user_id !== $user->id) {
            throw new RuntimeException(message: 'This social account is already linked to a different Vaulta user.');
        }

        /** @var OAuthAccount $account */
        $account = OAuthAccount::query()->updateOrCreate(
            attributes: [
                'provider' => $userData->provider,
                'provider_id' => $userData->providerId,
            ],
            values: [
                'user_id' => $user->id,
                'provider_email' => $userData->email,
                'name' => $userData->name,
                'nickname' => $userData->nickname,
                'avatar' => $userData->avatar,
                'access_token' => $userData->accessToken,
                'refresh_token' => $userData->refreshToken,
                'token_expires_at' => $userData->expiresAt,
            ],
        );

        if ($user->avatar === null && $userData->avatar !== null) {
            $user->forceFill(attributes: ['avatar' => $userData->avatar])->save();
        }

        if ($markEmailVerified && $user->email_verified_at === null) {
            $user->forceFill(attributes: ['email_verified_at' => now()])->save();
        }

        return $account;
    }
}
