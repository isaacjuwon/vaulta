<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\Palette;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

final readonly class RegisterUser
{
    public function handle(string $name, string $email, string $password): User
    {
        $attributes = [
            'name' => trim(string: $name),
            'email' => mb_strtolower(string: trim(string: $email)),
            'password' => $password,
        ];

        if (! SiteSetting::requiresEmailVerification()) {
            $attributes['email_verified_at'] = now();
        }

        $user = User::query()->create(attributes: $attributes);

        $user->forceFill(attributes: [
            'palette' => Palette::default()->value,
        ])->save();

        Event::dispatch(event: new Registered(user: $user));

        return $user;
    }
}
