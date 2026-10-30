<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Palette;
use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            attributes: ['email' => 'admin@vaulta.test'],
            values: [
                'name' => 'Vaulta Admin',
                'password' => 'password',
                'palette' => Palette::Ferrite->value,
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        if (Authenticator::query()->ownedBy(user: $admin)->doesntExist()) {
            Authenticator::factory()->create(attributes: [
                'user_id' => $admin->id,
                'issuer' => 'GitHub',
                'account' => 'admin@vaulta.test',
                'color' => TagColor::Emerald->value,
                'is_favorite' => true,
            ]);

            Authenticator::factory()->create(attributes: [
                'user_id' => $admin->id,
                'issuer' => 'Cloudflare',
                'account' => 'infra@vaulta.test',
                'color' => TagColor::Amber->value,
                'is_favorite' => false,
            ]);
        }

        if (Faq::query()->doesntExist()) {
            foreach (Faq::defaults() as $faq) {
                Faq::query()->create(attributes: $faq);
            }
        }
    }
}
