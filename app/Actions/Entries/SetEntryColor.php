<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\TagColor;
use App\Models\Authenticator;

final readonly class SetEntryColor
{
    public function handle(Authenticator $entry, ?TagColor $color): Authenticator
    {
        $resolved = $color ?? TagColor::default();

        if ($entry->resolvedColor() !== $resolved) {
            $entry->update(attributes: [
                'color' => $resolved,
            ]);
        }

        return $entry;
    }
}
