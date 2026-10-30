<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;

final readonly class UnblockManagedUser
{
    public function handle(int $targetUserId): bool
    {
        $target = User::query()->findOrFail(id: $targetUserId);

        if (! $target->is_blocked) {
            return false;
        }

        $target->forceFill(attributes: [
            'is_blocked' => false,
            'blocked_at' => null,
            'sign_in_locked_until' => null,
        ])->save();

        return true;
    }
}
