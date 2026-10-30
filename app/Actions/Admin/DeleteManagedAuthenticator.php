<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Authenticator;

final readonly class DeleteManagedAuthenticator
{
    public function handle(int $authenticatorId): void
    {
        Authenticator::query()
            ->withoutSecrets()
            ->findOrFail(id: $authenticatorId)
            ->delete();
    }
}
