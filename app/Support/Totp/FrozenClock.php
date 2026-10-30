<?php

declare(strict_types=1);

namespace App\Support\Totp;

use Override;

final readonly class FrozenClock implements Clock
{
    public function __construct(
        private int $frozenAt,
    ) {}

    #[Override]
    public function timestamp(): int
    {
        return $this->frozenAt;
    }
}
