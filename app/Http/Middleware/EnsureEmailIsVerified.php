<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (! SiteSetting::requiresEmailVerification()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? abort(code: 403, message: 'Your email address is not verified.')
                : Redirect::guest(path: URL::route($redirectToRoute ?: 'verification.notice'));
        }

        return $next($request);
    }
}
