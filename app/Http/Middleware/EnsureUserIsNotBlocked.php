<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureUserIsNotBlocked
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_blocked) {
            Auth::guard(name: 'web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route(route: 'login')
                ->withErrors(provider: [
                    'email' => 'This account is unavailable. Contact an administrator for help.',
                ]);
        }

        return $next($request);
    }
}
