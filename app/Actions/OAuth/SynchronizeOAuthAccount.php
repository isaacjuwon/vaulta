<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Data\OAuth\OAuthUserData;
use App\Models\OAuthAccount;
use App\Models\User;

final readonly class SynchronizeOAuthAccount
{
    /**
     * Refresh OAuth tokens, expiration and profile details for an existing linked account.
     */
    public function handle(OAuthAccount $account, OAuthUserData $userData): User
    {
        $account->update(attributes: [
            'provider_email' => $userData->email,
            'name' => $userData->name,
            'nickname' => $userData->nickname,
            'avatar' => $userData->avatar,
            'access_token' => $userData->accessToken,
            'refresh_token' => $userData->refreshToken,
            'token_expires_at' => $userData->expiresAt,
        ]);

        return $account->user;
    }
}
