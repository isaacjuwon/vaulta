<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Models\Authenticator;

final readonly class SetEntryFavorite
{
    public function handle(Authenticator $entry, bool $favorite): Authenticator
    {
        if ($entry->is_favorite !== $favorite) {
            $entry->update(attributes: [
                'is_favorite' => $favorite,
            ]);
        }

        return $entry;
    }
}
