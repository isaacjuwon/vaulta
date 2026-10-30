<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use App\Support\Totp\Base32;
use Illuminate\Validation\ValidationException;

final readonly class AddEntry
{
    /**
     * @throws ValidationException
     */
    public function handle(
        User $user,
        string $issuer,
        string $account,
        string $secret,
        ?TagColor $color = null,
    ): Authenticator {
        $normalizedSecret = Base32::normalize(input: $secret);

        if (! Base32::isValidSecret(input: $normalizedSecret)) {
            throw ValidationException::withMessages(messages: [
                'secret' => 'Secret key must be at least 16 Base32 characters.',
            ]);
        }

        return $user->authenticators()->create(attributes: [
            'issuer' => trim(string: $issuer),
            'account' => trim(string: $account),
            'secret' => $normalizedSecret,
            'color' => $color ?? TagColor::default(),
            'is_favorite' => false,
        ]);
    }
}
