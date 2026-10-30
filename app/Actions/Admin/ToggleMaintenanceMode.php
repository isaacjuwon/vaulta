<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

final readonly class ToggleMaintenanceMode
{
    public function handle(bool $enabled, ?string $secret = null, ?string $message = null, int $retry = 60): void
    {
        if ($enabled) {
            $secret = filled(value: $secret) ? trim(string: (string) $secret) : Str::random(length: 32);
            $message = filled(value: $message) ? trim(string: (string) $message) : 'Vaulta is currently undergoing scheduled maintenance.';
            $retry = max(15, $retry);

            SiteSetting::set(key: 'maintenance_secret', value: $secret);
            SiteSetting::set(key: 'maintenance_message', value: $message);
            SiteSetting::set(key: 'maintenance_retry', value: $retry);

            Artisan::call(command: 'down', parameters: [
                '--secret' => $secret,
                '--status' => 503,
                '--retry' => $retry,
                '--render' => 'errors::503',
            ]);
        } else {
            Artisan::call(command: 'up');
            SiteSetting::forget(key: 'maintenance_secret');
            SiteSetting::forget(key: 'maintenance_message');
            SiteSetting::forget(key: 'maintenance_retry');
        }
    }
}
