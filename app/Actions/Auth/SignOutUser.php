<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final readonly class SignOutUser
{
    public function handle(): void
    {
        Auth::guard(name: 'web')->logout();
        Session::invalidate();
        Session::regenerateToken();
    }
}
