<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Enums\OAuthProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class Redirector
{
    /**
     * Redirect the user to the OAuth provider's authorization page.
     *
     * @param  array<string, mixed>  $additionalParams
     */
    public function handle(OAuthProvider $provider, ?string $intended = null, array $additionalParams = []): RedirectResponse
    {
        if (! $provider->isEnabled()) {
            throw new InvalidArgumentException(message: "OAuth provider [{$provider->label()}] is not configured or enabled.");
        }

        $state = Str::random(length: 40);
        Session::put(key: 'oauth.state', value: $state);
        Session::put(key: 'oauth.provider', value: $provider->value);

        if ($intended !== null) {
            Session::put(key: 'oauth.intended', value: $intended);
        }

        if (Auth::check()) {
            Session::put(key: 'oauth.connecting_user_id', value: Auth::id());
        } else {
            Session::forget('oauth.connecting_user_id');
        }

        $codeChallenge = null;
        if ($provider->usePkce()) {
            $codeVerifier = Str::random(length: 64);
            Session::put(key: 'oauth.code_verifier', value: $codeVerifier);
            $codeChallenge = rtrim(string: strtr(string: base64_encode(string: hash(algo: 'sha256', data: $codeVerifier, binary: true)), from: '+/', to: '-_'), characters: '=');
        }

        $authorizationUrl = $provider->buildAuthorizationUrl(
            state: $state,
            codeChallenge: $codeChallenge,
            additional: $additionalParams,
        );

        return redirect()->away(path: $authorizationUrl);
    }
}
