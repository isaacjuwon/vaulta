<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\Palette;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

final readonly class SiteSettings
{
    /**
     * @param  array<string, string>  $socialLinks
     */
    public function __construct(
        public string $name,
        public string $supportEmail,
        public string $issuer,
        public string $registrationMode,
        public Palette $defaultTheme,
        public bool $allowPlainExport,
        public bool $requireEmailVerification,
        public ?string $logoUrl,
        public ?string $faviconUrl,
        public string $copyright,
        public string $tagline,
        public array $socialLinks,
        public string $description = 'Enterprise self-hosted two-factor authenticator with zero telemetry.',
        public int $sessionLifetime = 120,
        public int $maxLoginAttempts = 5,
    ) {}

    /**
     * Load current site settings from cache or database.
     */
    public static function current(): self
    {
        /** @var array<string, mixed> $raw */
        $raw = Cache::remember(
            key: 'site_settings.cache',
            ttl: 3600,
            callback: static function (): array {
                try {
                    return SiteSetting::query()->pluck(column: 'value', key: 'key')->all();
                } catch (\Throwable) {
                    return [];
                }
            },
        );

        $name = (string) ($raw['site_name'] ?? config(key: 'app.name', default: 'Vaulta'));
        $themeValue = (string) ($raw['default_theme'] ?? 'ferrite');

        $socialLinks = [];
        foreach (['github', 'twitter', 'discord', 'bluesky'] as $network) {
            $url = (string) ($raw['social_'.$network] ?? '');
            if (trim(string: $url) !== '') {
                $socialLinks[$network] = trim(string: $url);
            }
        }

        return new self(
            name: $name,
            supportEmail: (string) ($raw['support_email'] ?? 'admin@vaulta.local'),
            issuer: (string) ($raw['issuer_name'] ?? config(key: 'vaulta.issuer', default: 'Vaulta')),
            registrationMode: (string) ($raw['registration_mode'] ?? 'open'),
            defaultTheme: Palette::tryFrom(value: $themeValue) ?? Palette::Ferrite,
            allowPlainExport: filter_var(value: $raw['allow_plain_export'] ?? true, filter: FILTER_VALIDATE_BOOLEAN),
            requireEmailVerification: filter_var(
                value: $raw['require_email_verification'] ?? config(key: 'vaulta.require_email_verification', default: true),
                filter: FILTER_VALIDATE_BOOLEAN,
            ),
            logoUrl: filled($raw['site_logo'] ?? null) ? (string) $raw['site_logo'] : null,
            faviconUrl: filled($raw['site_favicon'] ?? null) ? (string) $raw['site_favicon'] : null,
            copyright: filled($raw['footer_copyright'] ?? null) ? (string) $raw['footer_copyright'] : $name.' · AGPL-3.0',
            tagline: filled($raw['footer_tagline'] ?? null) ? (string) $raw['footer_tagline'] : 'RFC 6238 · Zero telemetry',
            socialLinks: $socialLinks,
            description: filled($raw['site_description'] ?? null) ? (string) $raw['site_description'] : 'Enterprise self-hosted two-factor authenticator with zero telemetry.',
            sessionLifetime: (int) ($raw['session_lifetime'] ?? 120),
            maxLoginAttempts: (int) ($raw['max_login_attempts'] ?? 5),
        );
    }

    /**
     * Purge the cached site settings.
     */
    public static function purge(): void
    {
        Cache::forget(key: 'site_settings.cache');
        Cache::forget(key: 'site_settings.all');
    }
}
