<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Palette;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(value: 'password'),
            'palette' => Palette::default()->value,
            'avatar' => null,
            'is_admin' => false,
            'is_blocked' => false,
            'blocked_at' => null,
            'sign_in_locked_until' => null,
            'remember_token' => Str::random(length: 10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'two_factor_secret' => encrypt(value: 'JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(value: json_encode(value: ['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'is_blocked' => true,
            'blocked_at' => now(),
        ]);
    }

    public function withPalette(Palette $palette): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'palette' => $palette->value,
        ]);
    }
}
