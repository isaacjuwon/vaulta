<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;

final readonly class UpdateUserProfile
{
    public function handle(User $user, string $name, string $email): User
    {
        $user->fill(attributes: [
            'name' => trim(string: $name),
            'email' => mb_strtolower(string: trim(string: $email)),
        ]);

        if ($user->isDirty(attributes: 'email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }
}
