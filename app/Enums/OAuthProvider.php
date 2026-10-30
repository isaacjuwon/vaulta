<?php

declare(strict_types=1);

namespace App\Enums;

use App\Data\OAuth\OAuthUserData;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

enum OAuthProvider: string
{
    case GitHub = 'github';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::Google => 'Google',
        };
    }

    public function authorizationUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://github.com/login/oauth/authorize',
            self::Google => 'https://accounts.google.com/o/oauth2/v2/auth',
        };
    }

    public function tokenUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://github.com/login/oauth/access_token',
            self::Google => 'https://oauth2.googleapis.com/token',
        };
    }

    public function userUrl(): string
    {
        return match ($this) {
            self::GitHub => 'https://api.github.com/user',
            self::Google => 'https://openidconnect.googleapis.com/v1/userinfo',
        };
    }

    public function emailsUrl(): ?string
    {
        return match ($this) {
            self::GitHub => 'https://api.github.com/user/emails',
            self::Google => null,
        };
    }

    /**
     * @return list<string>
     */
    public function defaultScopes(): array
    {
        return match ($this) {
            self::GitHub => ['read:user', 'user:email'],
            self::Google => ['openid', 'profile', 'email'],
        };
    }

    public function clientId(): ?string
    {
        $dynamic = SiteSetting::get(key: 'oauth_'.$this->value.'_client_id');
        if (filled(value: $dynamic)) {
            return (string) $dynamic;
        }

        $fromConfig = config(key: 'services.'.$this->value.'.client_id');

        return filled(value: $fromConfig) ? (string) $fromConfig : null;
    }

    public function clientSecret(): ?string
    {
        $dynamic = SiteSetting::get(key: 'oauth_'.$this->value.'_client_secret');
        if (filled(value: $dynamic)) {
            return (string) $dynamic;
        }

        $fromConfig = config(key: 'services.'.$this->value.'.client_secret');

        return filled(value: $fromConfig) ? (string) $fromConfig : null;
    }

    public function redirectUri(): string
    {
        return route(name: 'oauth.callback', parameters: ['provider' => $this->value]);
    }

    public function isEnabled(): bool
    {
        $explicitSetting = SiteSetting::get(key: 'oauth_'.$this->value.'_enabled');
        if ($explicitSetting !== null) {
            return filter_var(value: $explicitSetting, filter: FILTER_VALIDATE_BOOLEAN) && ! empty($this->clientId()) && ! empty($this->clientSecret());
        }

        return ! empty($this->clientId()) && ! empty($this->clientSecret());
    }

    public function usePkce(): bool
    {
        return match ($this) {
            self::Google => true,
            self::GitHub => false,
        };
    }

    /**
     * Build standard OAuth token exchange request payload.
     *
     * @return array<string, string>
     */
    public function tokenPayload(string $code, ?string $codeVerifier = null): array
    {
        $payload = [
            'client_id' => (string) $this->clientId(),
            'client_secret' => (string) $this->clientSecret(),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ];

        if ($this->usePkce() && filled(value: $codeVerifier)) {
            $payload['code_verifier'] = (string) $codeVerifier;
        }

        return $payload;
    }

    /**
     * Build provider-specific authorization parameters including scopes, state, and PKCE.
     *
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    public function authorizationParameters(string $state, ?string $codeChallenge = null, array $additional = []): array
    {
        $params = [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(separator: ' ', array: $this->defaultScopes()),
            'state' => $state,
            ...$additional,
        ];

        if ($this->usePkce() && filled(value: $codeChallenge)) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        if ($this === self::Google) {
            $params['access_type'] = 'offline';
            $params['prompt'] = 'select_account';
        }

        return $params;
    }

    /**
     * Build the full provider authorization redirect URL.
     *
     * @param  array<string, mixed>  $additional
     */
    public function buildAuthorizationUrl(string $state, ?string $codeChallenge = null, array $additional = []): string
    {
        $params = $this->authorizationParameters(state: $state, codeChallenge: $codeChallenge, additional: $additional);
        $query = http_build_query(data: $params, numeric_prefix: '', arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

        return $this->authorizationUrl().'?'.$query;
    }

    /**
     * Fetch the raw user profile from the provider API.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function fetchUserProfile(string $accessToken): array
    {
        $response = Http::withToken(token: $accessToken)
            ->acceptJson()
            ->timeout(seconds: 15)
            ->get(url: $this->userUrl());

        if (! $response->successful()) {
            throw new RuntimeException(message: "Failed to fetch user profile from {$this->label()} API [{$response->status()}].");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        // GitHub secondary email resolution if primary email is private
        if ($this === self::GitHub && (! isset($data['email']) || blank(value: $data['email']))) {
            $primaryEmail = $this->fetchGitHubPrimaryEmail(accessToken: $accessToken);
            if ($primaryEmail !== null) {
                $data['email'] = $primaryEmail;
            }
        }

        return $data;
    }

    /**
     * Resolve verified primary email from GitHub's emails API endpoint.
     */
    private function fetchGitHubPrimaryEmail(string $accessToken): ?string
    {
        $url = $this->emailsUrl();
        if ($url === null) {
            return null;
        }

        $response = Http::withToken(token: $accessToken)
            ->acceptJson()
            ->timeout(seconds: 10)
            ->get(url: $url);

        if (! $response->successful()) {
            return null;
        }

        /** @var list<array{email: string, primary: bool, verified: bool}> $emails */
        $emails = $response->json();
        foreach ($emails as $emailItem) {
            if ($emailItem['primary'] && $emailItem['verified']) {
                return (string) $emailItem['email'];
            }
        }

        return null;
    }

    /**
     * Map provider-specific raw payload to normalized OAuthUserData.
     *
     * @param  array<string, mixed>  $rawUser
     *
     * @throws RuntimeException
     */
    public function toUserData(
        array $rawUser,
        string $accessToken,
        ?string $refreshToken = null,
        ?CarbonImmutable $expiresAt = null,
    ): OAuthUserData {
        $providerId = match ($this) {
            self::GitHub => (string) ($rawUser['id'] ?? ''),
            self::Google => (string) ($rawUser['sub'] ?? $rawUser['id'] ?? ''),
        };

        if ($providerId === '') {
            throw new RuntimeException(message: "Provider [{$this->label()}] returned an invalid or empty user identifier.");
        }

        $name = isset($rawUser['name']) ? (string) $rawUser['name'] : null;

        $nickname = match ($this) {
            self::GitHub => isset($rawUser['login']) ? (string) $rawUser['login'] : null,
            self::Google => null,
        };

        $avatar = match ($this) {
            self::GitHub => isset($rawUser['avatar_url']) ? (string) $rawUser['avatar_url'] : null,
            self::Google => isset($rawUser['picture']) ? (string) $rawUser['picture'] : null,
        };

        $email = isset($rawUser['email']) && filled(value: $rawUser['email']) ? (string) $rawUser['email'] : null;

        return new OAuthUserData(
            provider: $this,
            providerId: $providerId,
            email: $email !== null ? mb_strtolower(string: trim(string: $email)) : null,
            name: $name,
            nickname: $nickname,
            avatar: $avatar,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
        );
    }

    /**
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(
            array_filter(
                array: self::cases(),
                callback: static fn (self $provider): bool => $provider->isEnabled(),
            ),
        );
    }
}
