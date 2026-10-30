<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Data\OAuth\OAuthUserData;
use App\Enums\OAuthProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use RuntimeException;

final readonly class Verifier
{
    /**
     * Verify the OAuth authorization callback and return verified user profile data.
     *
     * @throws RuntimeException
     */
    public function handle(Request $request, OAuthProvider $provider): OAuthUserData
    {
        $this->ensureNoError(request: $request);
        $this->verifyState(request: $request);

        $code = $this->extractAuthorizationCode(request: $request);
        $codeVerifier = $provider->usePkce() ? (string) Session::pull(key: 'oauth.code_verifier') : null;

        [$accessToken, $refreshToken, $expiresAt] = $this->exchangeToken(
            provider: $provider,
            code: $code,
            codeVerifier: $codeVerifier,
        );

        $rawUser = $provider->fetchUserProfile(accessToken: $accessToken);

        return $provider->toUserData(
            rawUser: $rawUser,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
        );
    }

    private function ensureNoError(Request $request): void
    {
        $error = $request->query(key: 'error');
        if (filled(value: $error)) {
            $description = (string) ($request->query(key: 'error_description') ?? $error);
            throw new RuntimeException(message: "OAuth authorization failed: {$description}");
        }
    }

    private function verifyState(Request $request): void
    {
        $sessionState = Session::pull(key: 'oauth.state');
        $callbackState = $request->query(key: 'state');

        if (empty($sessionState) || empty($callbackState) || ! hash_equals(known_string: (string) $sessionState, user_string: (string) $callbackState)) {
            throw new RuntimeException(message: 'Invalid OAuth state parameter. Possible CSRF attempt.');
        }
    }

    private function extractAuthorizationCode(Request $request): string
    {
        $code = $request->query(key: 'code');
        if (! is_string(value: $code) || trim(string: $code) === '') {
            throw new RuntimeException(message: 'Authorization code was not provided in the callback request.');
        }

        return trim(string: $code);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?CarbonImmutable}
     *
     * @throws RuntimeException
     */
    private function exchangeToken(OAuthProvider $provider, string $code, ?string $codeVerifier): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(seconds: 15)
            ->post(url: $provider->tokenUrl(), data: $provider->tokenPayload(code: $code, codeVerifier: $codeVerifier));

        if (! $response->successful()) {
            $msg = (string) ($response->json(key: 'error_description') ?? $response->json(key: 'error') ?? 'Token exchange request failed.');
            throw new RuntimeException(message: "Token exchange error: {$msg}");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException(message: "Provider [{$provider->label()}] did not return an access token.");
        }

        $refreshToken = isset($data['refresh_token']) ? (string) $data['refresh_token'] : null;
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;
        $expiresAt = $expiresIn !== null ? CarbonImmutable::now()->addSeconds(value: $expiresIn) : null;

        return [$accessToken, $refreshToken, $expiresAt];
    }
}
