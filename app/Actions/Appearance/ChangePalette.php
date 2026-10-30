<?php

declare(strict_types=1);

namespace App\Actions\Appearance;

use App\Enums\Palette;
use App\Models\User;

final readonly class ChangePalette
{
    public function handle(User $user, Palette $palette): User
    {
        $user->forceFill(attributes: [
            'palette' => $palette->value,
        ])->save();

        return $user;
    }
}
