<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\OAuth\Redirector;
use App\Actions\OAuth\Verifier;
use App\Enums\OAuthProvider;
use App\Jobs\CreateOAuthUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Throwable;

final class OAuthController extends Controller
{
    /**
     * Redirect to the specified OAuth provider.
     */
    public function redirect(OAuthProvider $provider, Redirector $redirector): RedirectResponse
    {
        if (! $provider->isEnabled()) {
            return redirect()
                ->route(route: Auth::check() ? 'settings' : 'login')
                ->withErrors(provider: ['oauth' => __(':provider authentication is not enabled or configured.', ['provider' => $provider->label()])]);
        }

        return $redirector->handle(provider: $provider);
    }

    /**
     * Handle the OAuth provider callback.
     */
    public function callback(Request $request, OAuthProvider $provider, Verifier $verifier): RedirectResponse
    {
        try {
            $userData = $verifier->handle(request: $request, provider: $provider);
            $connectingUserId = Session::pull(key: 'oauth.connecting_user_id');

            $job = new CreateOAuthUserData(
                userData: $userData,
                connectingUserId: is_numeric(value: $connectingUserId) ? (int) $connectingUserId : null,
            );
            $user = $job->handle();

            if ($connectingUserId !== null) {
                return redirect()
                    ->route(route: 'settings', parameters: ['tab' => 'oauth'])
                    ->with(key: 'status', value: __(':provider account connected successfully.', ['provider' => $provider->label()]));
            }

            if ($user->is_blocked) {
                return redirect()
                    ->route(route: 'login')
                    ->withErrors(provider: ['email' => __('Your account has been blocked by an administrator.')]);
            }

            Auth::login(user: $user, remember: true);
            $request->session()->regenerate();

            /** @var string $intended */
            $intended = Session::pull(key: 'oauth.intended', default: route(name: 'vault'));

            return redirect()->intended(default: $intended);
        } catch (Throwable $exception) {
            $fallbackRoute = Auth::check() ? route(name: 'settings', parameters: ['tab' => 'oauth']) : route(name: 'login');

            return redirect()
                ->to(path: $fallbackRoute)
                ->withErrors(provider: ['oauth' => $exception->getMessage()]);
        }
    }
}
