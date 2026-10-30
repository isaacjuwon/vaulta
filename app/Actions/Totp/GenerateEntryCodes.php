<?php

declare(strict_types=1);

namespace App\Actions\Totp;

use App\Models\Authenticator;
use App\Support\Totp\Totp;
use App\Support\Totp\TotpCode;
use Illuminate\Support\Collection;

final readonly class GenerateEntryCodes
{
    public function __construct(
        private Totp $totp = new Totp,
    ) {}

    /**
     * Decrypt each entry's secret and compute its current TOTP code keyed by entry ID.
     *
     * @param  iterable<int, Authenticator>  $entries
     * @return Collection<int, TotpCode>
     */
    public function handle(iterable $entries, ?int $at = null): Collection
    {
        /** @var array<int, TotpCode> $codes */
        $codes = [];

        foreach ($entries as $entry) {
            $codes[$entry->id] = $this->totp->generate(
                base32Secret: $entry->secret,
                at: $at,
            );
        }

        return collect(value: $codes);
    }

    public function remainingSeconds(?int $at = null): int
    {
        return $this->totp->remainingSeconds(at: $at);
    }
}
