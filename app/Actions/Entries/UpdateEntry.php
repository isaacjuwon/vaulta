<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Support\Totp\Base32;
use Illuminate\Validation\ValidationException;

final readonly class UpdateEntry
{
    /**
     * @throws ValidationException
     */
    public function handle(
        Authenticator $entry,
        string $issuer,
        string $account,
        ?TagColor $color = null,
        ?string $secret = null,
    ): Authenticator {
        $trimmedIssuer = trim(string: $issuer);
        $trimmedAccount = trim(string: $account);

        if ($trimmedIssuer === '') {
            throw ValidationException::withMessages(messages: [
                'issuer' => 'Service name is required.',
            ]);
        }

        if ($trimmedAccount === '') {
            throw ValidationException::withMessages(messages: [
                'account' => 'Account label is required.',
            ]);
        }

        $attributes = [
            'issuer' => $trimmedIssuer,
            'account' => $trimmedAccount,
            'color' => $color ?? $entry->tagColor(),
        ];

        if ($secret !== null && trim(string: $secret) !== '') {
            $normalizedSecret = Base32::normalize(input: $secret);

            if (! Base32::isValidSecret(input: $normalizedSecret)) {
                throw ValidationException::withMessages(messages: [
                    'secret' => 'Secret key must be at least 16 Base32 characters.',
                ]);
            }

            $attributes['secret'] = $normalizedSecret;
        }

        $entry->update(attributes: $attributes);

        return $entry->fresh() ?? $entry;
    }
}
