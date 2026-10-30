<?php

declare(strict_types=1);

use App\Models\Authenticator;
use App\Support\IssuerBrand;

test('issuer brand detects known providers with case insensitivity and domains', function (string $input, string $expectedBrand): void {
    expect(IssuerBrand::detect(issuer: $input))->toBe(expected: $expectedBrand);
})->with([
    'GitHub simple' => ['GitHub', 'github'],
    'GitHub lowercase' => ['github', 'github'],
    'GitHub domain' => ['github.com', 'github'],
    'Google account' => ['Google Account', 'google'],
    'Gmail' => ['Gmail', 'google'],
    'Cloudflare' => ['Cloudflare', 'cloudflare'],
    'AWS' => ['AWS', 'aws'],
    'Amazon' => ['Amazon Web Services', 'aws'],
    'Microsoft' => ['Microsoft', 'microsoft'],
    'Azure' => ['Azure AD', 'microsoft'],
    'Discord' => ['Discord', 'discord'],
    'GitLab' => ['GitLab.com', 'gitlab'],
    'Slack' => ['Slack Technologies', 'slack'],
    'Stripe' => ['Stripe Dashboard', 'stripe'],
    'DigitalOcean' => ['DigitalOcean', 'digitalocean'],
    'Apple' => ['Apple ID', 'apple'],
    'Proton' => ['ProtonMail', 'proton'],
    'Fastmail' => ['Fastmail', 'fastmail'],
    'Dropbox' => ['Dropbox', 'dropbox'],
    'Bitbucket' => ['Bitbucket', 'bitbucket'],
    'Linear' => ['Linear', 'linear'],
    'Meta' => ['Facebook', 'meta'],
    'X' => ['Twitter', 'x'],
]);

test('issuer brand returns null for unknown custom or empty issuers', function (): void {
    expect(IssuerBrand::detect(issuer: 'My Internal LDAP'))->toBeNull()
        ->and(IssuerBrand::detect(issuer: 'Custom Self-Hosted Server'))->toBeNull()
        ->and(IssuerBrand::detect(issuer: ''))->toBeNull()
        ->and(IssuerBrand::detect(issuer: '   '))->toBeNull();
});

test('authenticator initials helper falls back gracefully for unknown issuers', function (): void {
    expect(Authenticator::initialsFor(issuer: 'My Internal LDAP'))->toBe(expected: 'MI')
        ->and(Authenticator::initialsFor(issuer: 'SingleWord'))->toBe(expected: 'S')
        ->and(Authenticator::initialsFor(issuer: ''))->toBe(expected: 'A');
});
