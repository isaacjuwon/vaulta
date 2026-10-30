# Vaulta

[![PHP Version](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel Framework](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4.x%20SFC-FB70A9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Flux UI](https://img.shields.io/badge/Flux%20UI-v2-4F46E5)](https://fluxui.dev)
[![WebAuthn Passkeys](https://img.shields.io/badge/WebAuthn-Passkeys-0A84FF?logo=fidoalliance&logoColor=white)](https://fidoalliance.org)
[![OAuth 2.0](https://img.shields.io/badge/OAuth%202.0-GitHub%20%26%20Google-black)](https://oauth.net/2/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)

> **Your two-factor codes, stored under your own roof.**

Vaulta is a self-hosted, sovereign 2FA authenticator vault engineered for high security and low-friction access across desktop and mobile. Built with **Laravel 13**, **Livewire 4 Single-File Components**, **Flux UI v2**, and **Tailwind CSS**, Vaulta combines hardware-grade authentication (**FIDO2 / WebAuthn Passkeys**, Fortify 2FA, and native **OAuth 2.0 PKCE** for GitHub & Google) with a completely self-hosted, air-gapped tactile theme engine ported from Ferrite.

---

### Technical Highlights & Architecture

- **Cryptographic Security at Rest**: All TOTP/HOTP shared secrets are encrypted using authenticated **AES-256-GCM** encryption keyed to your unique `APP_KEY`. Plaintext secrets are decrypted in-memory strictly when generating time-slice tokens and are never exposed to browser state.
- **Pure-PHP RFC 6238 / RFC 4226 Engine**: High-precision TOTP implementation supporting `SHA1`, `SHA256`, and `SHA512` hashing algorithms, `6` or `8` code digits, custom timestep windows (`15`s to `120`s), and drift correction without external C extensions or microservices.
- **Livewire 4 Scoped Code Islands**: Uses the `@island(name: 'codes')` reactive primitive to restrict periodic 30-second token refreshes strictly to active timer badges. Search filtering, palette selectors, tag managers, and navigation remain 100% stable without DOM thrashing or state re-hydration.
- **Hardware Passkeys & Native OAuth 2.0**: Sign in passwordlessly via FIDO2 / WebAuthn passkeys (TouchID, FaceID, YubiKey) or link external identity providers (GitHub, Google) with full administrator visibility and individual provider revocation.
- **15 Air-Gapped Ferrite Themes**: Includes 15 tactile retro-computing themes (`ferrite`, `ferrite-light`, `mac`, `desk95`, `zine`, `bubblegum`, `memphis`, `nokia`, `amber`, `phosphor`, `commodore`, `miami`, `amiga`, `wallstreet`, `lotus`) and 18 WOFF2 font families packaged locally with zero external Google Fonts, CDN, or third-party tracking calls.
- **PWA & Offline Capability**: Progressive Web App manifest, custom service worker, and local in-browser QR scanning via the hardware-accelerated `BarcodeDetector` API.
- **Admin Governance & Observability**: Real-time instance telemetry, tenant user provisioning, account lockouts, authenticator label updates (with cryptographic secret redaction), active database session management, and site-wide maintenance modes.

### Tags / Topics

`laravel` · `livewire-4` · `totp` · `authenticator` · `2fa` · `two-factor-authentication` · `webauthn` · `passkeys` · `oauth2` · `flux-ui` · `cryptography` · `self-hosted` · `pwa` · `security` · `privacy` · `php85`

---

## Features

- **Pure-PHP RFC 6238 TOTP Engine**: Supports `SHA1`, `SHA256`, and `SHA512`, `6` or `8` digits, custom periods (`15`–`120`s), `otpauth://totp/...` URI import, and local in-browser QR image decoding via `BarcodeDetector`.
- **Livewire 4 Scoped Code Islands**: Only the `@island(name: 'codes')` region refreshes at TOTP period boundaries—search inputs, color filters, and modals stay untouched, while secrets never leave the server.
- **15 Self-Hosted Themes**: All 15 palettes (`ferrite`, `ferrite-light`, `mac`, `desk95`, `zine`, `bubblegum`, `memphis`, `nokia`, `amber`, `phosphor`, `commodore`, `miami`, `amiga`, `wallstreet`, `lotus`) and 18 WOFF2 font families ship inside the repository with zero runtime requests to Google Fonts or external CDNs.
- **Hardened Account Security**: WebAuthn Passkeys, Fortify 2FA + recovery codes, active device session revocation, automatic sign-in lockouts, per-request CSP nonces, and encrypted-at-rest TOTP secrets.
- **Instance Administration**: Overview metrics, user management (create, edit, block/unblock, delete with last-admin protection), secret-redacted authenticator label management, and instance-wide session revocation.

## Requirements

- PHP **8.4+** (developed and verified on PHP **8.5**) with `mbstring`, `openssl`, `pdo_sqlite` (or `pdo_mysql` / `pdo_pgsql`)
- Composer 2.x
- Node.js 22+ & npm

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
npm install
npm run build
php artisan vaulta:check
```

> **CRITICAL — Back Up `APP_KEY`**: All authenticator secrets are encrypted with `APP_KEY`. Store a secure offline copy of `APP_KEY` immediately after running `php artisan key:generate`. Losing `APP_KEY` permanently renders stored TOTP secrets unrecoverable.

## Configuration (`.env`)

| Variable | Default | Description |
| --- | --- | --- |
| `VAULTA_REGISTRATION` | `open` | `open` (anyone can register), `first_user` (closes automatically after the first account), or `closed` (admin-provisioned accounts only). |
| `VAULTA_DEFAULT_PALETTE` | `ferrite` | Default theme slug for guests and newly created accounts. |
| `VAULTA_SIGN_IN_LOCKOUT_ATTEMPTS` | `10` | Failed sign-in attempts within 15 minutes before locking the account. |
| `VAULTA_SIGN_IN_LOCKOUT_MINUTES` | `15` | Lockout duration in minutes. |

## Artisan Commands

- `php artisan vaulta:check` — Verify environment, `APP_KEY`, database connectivity, and decryptability of every stored authenticator.
- `php artisan vaulta:grant-admin user@example.com` (or `--revoke`) — Promote or demote an administrator from the CLI.
- `php artisan vaulta:import-wellto /path/to/wellto/database.sqlite --old-key="base64:..."` — Re-encrypt and import users and authenticators from a legacy `wellto.io` SQLite database (`--dry-run` supported).

## Quality Gates

```bash
composer lint          # Format PHP & Blade SFCs with Laravel Pint
composer analyse       # PHPStan / Larastan level 8
composer contrast      # WCAG contrast check across all 15 theme CSS files
npm run build          # Production Vite + self-hosted WOFF2 font bundle
```

## Documentation

- [Implementation Plan](docs/IMPLEMENTATION_PLAN.md)
- [Feature Parity Matrix](docs/parity.md)
- [Theming System & Adding Themes](docs/themes.md)
- [Theme Token Inventory](docs/themes-inventory.md)
- [Security & Threat Model](docs/security.md)

## License

Vaulta is free and open-source software licensed under the **GNU Affero General Public License v3.0 or later (`AGPL-3.0-or-later`)**, matching Ferrite from which its 15-theme CSS and font system is derived. See [LICENSE](LICENSE) for the full text.
