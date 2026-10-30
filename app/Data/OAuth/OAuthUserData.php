<?php

declare(strict_types=1);

namespace App\Data\OAuth;

use App\Enums\OAuthProvider;
use Carbon\CarbonImmutable;

final readonly class OAuthUserData
{
    public function __construct(
        public OAuthProvider $provider,
        public string $providerId,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $nickname = null,
        public ?string $avatar = null,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
