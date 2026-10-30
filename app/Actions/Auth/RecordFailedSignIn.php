<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

final readonly class RecordFailedSignIn
{
    public const int ACCOUNT_FAILURE_LIMIT = 10;

    public const int FAILURE_WINDOW_SECONDS = 900;

    public const int SIGN_IN_LOCK_MINUTES = 15;

    public function handle(string $email, ?User $user = null): void
    {
        $normalizedEmail = mb_strtolower(string: trim(string: $email));
        $key = self::accountKey(email: $normalizedEmail);

        RateLimiter::hit(key: $key, decaySeconds: self::FAILURE_WINDOW_SECONDS);

        if ($user !== null && ! $user->is_blocked && RateLimiter::tooManyAttempts(key: $key, maxAttempts: self::ACCOUNT_FAILURE_LIMIT)) {
            $user->forceFill(attributes: [
                'sign_in_locked_until' => now()->addMinutes(value: self::SIGN_IN_LOCK_MINUTES),
            ])->save();

            RateLimiter::clear(key: $key);
        }
    }

    public static function accountKey(string $email): string
    {
        return 'login-account:'.hash(algo: 'sha256', data: mb_strtolower(string: trim(string: $email)));
    }
}
