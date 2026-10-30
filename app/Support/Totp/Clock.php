<?php

declare(strict_types=1);

namespace App\Support\Totp;

interface Clock
{
    /**
     * Return the current Unix epoch timestamp in seconds.
     */
    public function timestamp(): int;
}
