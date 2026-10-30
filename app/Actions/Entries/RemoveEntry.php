<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Models\Authenticator;

final readonly class RemoveEntry
{
    public function handle(Authenticator $entry): void
    {
        $entry->delete();
    }
}
