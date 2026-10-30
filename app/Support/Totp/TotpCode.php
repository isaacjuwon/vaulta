<?php

declare(strict_types=1);

namespace App\Support\Totp;

final readonly class TotpCode
{
    public function __construct(
        public string $value,
        public int $remainingSeconds,
        public int $period = Totp::PERIOD,
        public ?string $nextValue = null,
    ) {}

    /**
     * Format the six-digit code as "123 456" for display.
     */
    public function formatted(): string
    {
        if (strlen(string: $this->value) !== 6) {
            return $this->value;
        }

        return substr(string: $this->value, offset: 0, length: 3).' '.substr(string: $this->value, offset: 3);
    }

    /**
     * Format the next six-digit code as "123 456" for display.
     */
    public function formattedNext(): ?string
    {
        if ($this->nextValue === null || strlen(string: $this->nextValue) !== 6) {
            return $this->nextValue;
        }

        return substr(string: $this->nextValue, offset: 0, length: 3).' '.substr(string: $this->nextValue, offset: 3);
    }

    /**
     * Percentage (0.0 to 100.0) of the current window remaining.
     */
    public function remainingPercent(): float
    {
        return round(num: ($this->remainingSeconds / max(1, $this->period)) * 100, precision: 2);
    }
}
