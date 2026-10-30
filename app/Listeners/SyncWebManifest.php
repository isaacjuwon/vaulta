<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SiteSettingsUpdated;
use Illuminate\Support\Facades\File;

final class SyncWebManifest
{
    public function handle(SiteSettingsUpdated $event): void
    {
        try {
            $manifestPath = public_path(path: 'manifest.webmanifest');

            if (! File::exists(path: $manifestPath)) {
                return;
            }

            /** @var array<string, mixed> $data */
            $data = json_decode(json: (string) File::get(path: $manifestPath), associative: true) ?? [];

            $data['name'] = $event->settings->name.' Authenticator';
            $data['short_name'] = $event->settings->name;

            $favicon = $event->settings->faviconUrl;
            if ($favicon !== null && ! str_starts_with(haystack: $favicon, needle: 'http')) {
                /** @var list<array{src: string, sizes: string, type: string, purpose?: string}> $icons */
                $icons = $data['icons'] ?? [];
                $alreadyHas = false;
                foreach ($icons as $icon) {
                    if ($icon['src'] === $favicon) {
                        $alreadyHas = true;
                        break;
                    }
                }
                if (! $alreadyHas) {
                    array_unshift($icons, [
                        'src' => $favicon,
                        'sizes' => 'any',
                        'type' => str_ends_with(haystack: $favicon, needle: '.svg') ? 'image/svg+xml' : 'image/png',
                        'purpose' => 'any',
                    ]);
                    $data['icons'] = $icons;
                }
            }

            File::put(
                path: $manifestPath,
                contents: (string) json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );
        } catch (\Throwable) {
            // Fail gracefully if file write is disallowed
        }
    }
}
