<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\OAuthAccount;
use App\Models\User;

final readonly class UnlinkManagedUserOAuth
{
    /**
     * Unlink a specific OAuth provider identity from a managed user.
     */
    public function handle(User $actor, int $targetUserId, int $oauthAccountId): void
    {
        $account = OAuthAccount::query()
            ->where(column: 'user_id', operator: '=', value: $targetUserId)
            ->where(column: 'id', operator: '=', value: $oauthAccountId)
            ->firstOrFail();

        $account->delete();
    }
}
