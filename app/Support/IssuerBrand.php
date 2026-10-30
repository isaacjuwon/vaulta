<?php

declare(strict_types=1);

namespace App\Support;

final readonly class IssuerBrand
{
    /**
     * @var array<string, string>
     */
    private const array ALIAS_MAP = [
        'github' => 'github',
        'gh' => 'github',
        'google' => 'google',
        'gmail' => 'google',
        'gsuite' => 'google',
        'cloudflare' => 'cloudflare',
        'aws' => 'aws',
        'amazon' => 'aws',
        'amazon web services' => 'aws',
        'microsoft' => 'microsoft',
        'azure' => 'microsoft',
        'office365' => 'microsoft',
        'office 365' => 'microsoft',
        'outlook' => 'microsoft',
        'discord' => 'discord',
        'gitlab' => 'gitlab',
        'slack' => 'slack',
        'stripe' => 'stripe',
        'digitalocean' => 'digitalocean',
        'digital ocean' => 'digitalocean',
        'apple' => 'apple',
        'icloud' => 'apple',
        'proton' => 'proton',
        'protonmail' => 'proton',
        'proton mail' => 'proton',
        'fastmail' => 'fastmail',
        'dropbox' => 'dropbox',
        'bitbucket' => 'bitbucket',
        'linear' => 'linear',
        'meta' => 'meta',
        'facebook' => 'meta',
        'instagram' => 'meta',
        'x' => 'x',
        'twitter' => 'x',
    ];

    /**
     * Normalize the issuer string and resolve a known brand icon identifier if available.
     */
    public static function detect(string $issuer): ?string
    {
        $normalized = mb_strtolower(string: trim(string: $issuer));

        if ($normalized === '') {
            return null;
        }

        // Exact alias lookup
        if (isset(self::ALIAS_MAP[$normalized])) {
            return self::ALIAS_MAP[$normalized];
        }

        // Clean domains (e.g. "github.com" -> "github")
        $clean = preg_replace(pattern: '/\.(com|org|net|io|me|app|dev|cloud|co)$/i', replacement: '', subject: $normalized) ?? $normalized;
        if (isset(self::ALIAS_MAP[$clean])) {
            return self::ALIAS_MAP[$clean];
        }

        // Substring / word boundary search
        foreach (self::ALIAS_MAP as $needle => $brand) {
            if (str_contains(haystack: $normalized, needle: $needle)) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function knownBrands(): array
    {
        return array_values(array: array_unique(array: array_values(array: self::ALIAS_MAP)));
    }

    /**
     * Preset accounts with clean labels and canonical brand identifiers for quick selection.
     *
     * @return list<array{name: string, brand: string}>
     */
    public static function presets(): array
    {
        return [
            ['name' => 'GitHub', 'brand' => 'github'],
            ['name' => 'Google', 'brand' => 'google'],
            ['name' => 'Microsoft', 'brand' => 'microsoft'],
            ['name' => 'AWS', 'brand' => 'aws'],
            ['name' => 'Cloudflare', 'brand' => 'cloudflare'],
            ['name' => 'Discord', 'brand' => 'discord'],
            ['name' => 'Apple', 'brand' => 'apple'],
            ['name' => 'GitLab', 'brand' => 'gitlab'],
            ['name' => 'Slack', 'brand' => 'slack'],
            ['name' => 'Stripe', 'brand' => 'stripe'],
            ['name' => 'Proton', 'brand' => 'proton'],
            ['name' => 'Fastmail', 'brand' => 'fastmail'],
            ['name' => 'Dropbox', 'brand' => 'dropbox'],
            ['name' => 'Bitbucket', 'brand' => 'bitbucket'],
            ['name' => 'Linear', 'brand' => 'linear'],
            ['name' => 'Meta', 'brand' => 'meta'],
            ['name' => 'X', 'brand' => 'x'],
            ['name' => 'DigitalOcean', 'brand' => 'digitalocean'],
        ];
    }
}
