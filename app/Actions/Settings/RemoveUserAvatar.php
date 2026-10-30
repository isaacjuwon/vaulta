<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;

final readonly class RemoveUserAvatar
{
    public function handle(User $user): User
    {
        $user->forceFill(attributes: [
            'avatar' => null,
        ])->save();

        return $user;
    }
}
