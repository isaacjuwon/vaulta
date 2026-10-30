<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Authenticator;

final readonly class UpdateAuthenticatorMetadata
{
    public function handle(int $authenticatorId, string $issuer, string $account): Authenticator
    {
        $authenticator = Authenticator::query()
            ->withoutSecrets()
            ->findOrFail(id: $authenticatorId);

        $authenticator->update(attributes: [
            'issuer' => trim(string: $issuer),
            'account' => trim(string: $account),
        ]);

        return $authenticator;
    }
}
