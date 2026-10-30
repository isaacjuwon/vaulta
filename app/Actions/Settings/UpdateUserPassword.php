<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

final readonly class UpdateUserPassword
{
    public function __construct(
        private RevokeOtherUserSessions $revokeOtherSessions = new RevokeOtherUserSessions,
    ) {}

    public function handle(User $user, string $newPassword, ?string $currentSessionId = null): User
    {
        $user->forceFill(attributes: [
            'password' => $newPassword,
            'remember_token' => Str::random(length: 60),
        ])->save();

        Session::regenerate();

        $this->revokeOtherSessions->handle(
            user: $user,
            currentSessionId: $currentSessionId ?? Session::getId(),
        );

        return $user;
    }
}
