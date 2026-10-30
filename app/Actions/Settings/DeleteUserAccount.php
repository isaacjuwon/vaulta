<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Auth\SignOutUser;
use App\Models\User;

final readonly class DeleteUserAccount
{
    public function __construct(
        private SignOutUser $signOutUser = new SignOutUser,
    ) {}

    public function handle(User $user): void
    {
        $this->signOutUser->handle();
        $user->delete();
    }
}
