<?php

declare(strict_types=1);

namespace App\Support\Totp;

use Override;

final readonly class SystemClock implements Clock
{
    #[Override]
    public function timestamp(): int
    {
        return time();
    }
}
