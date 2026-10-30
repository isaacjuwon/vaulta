<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Palette;
use App\Models\User;

final readonly class CreateManagedUser
{
    public function handle(string $name, string $email, string $password, bool $isAdmin): User
    {
        $user = User::query()->create(attributes: [
            'name' => trim(string: $name),
            'email' => mb_strtolower(string: trim(string: $email)),
            'password' => $password,
        ]);

        $user->forceFill(attributes: [
            'palette' => Palette::default()->value,
            'is_admin' => $isAdmin,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
