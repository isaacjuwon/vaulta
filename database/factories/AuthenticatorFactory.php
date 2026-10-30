<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use App\Support\Totp\Base32;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Authenticator>
 */
final class AuthenticatorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issuer' => fake()->randomElement(array: ['GitHub', 'Cloudflare', 'Fastmail', 'Proton', 'Stripe', 'Linear']),
            'account' => fake()->safeEmail(),
            'secret' => Base32::generateSecret(),
            'color' => TagColor::default(),
            'is_favorite' => false,
        ];
    }

    public function favorite(): self
    {
        return $this->state(state: fn (array $attributes): array => [
            'is_favorite' => true,
        ]);
    }

    public function coloured(TagColor $color): self
    {
        return $this->state(state: fn (array $attributes): array => [
            'color' => $color,
        ]);
    }
}
