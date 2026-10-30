# Vaulta Security & Threat Model

Vaulta is a self-hosted RFC 6238 two-factor authenticator vault. This document states clearly what Vaulta protects against, what it does **not** protect against, and how to operate an instance safely.

## Encryption & Threat Model

- **At-rest encryption (`APP_KEY`)**: Every `Authenticator::$secret` (along with Fortify 2FA secrets and recovery codes) is encrypted at rest using Laravel's `encrypted` cast (`AES-256-CBC` with HMAC-SHA-256 authentication derived from `APP_KEY`).
- **Database-only compromise**: An attacker who obtains a SQL dump or SQLite file **without** `APP_KEY` cannot decrypt stored TOTP secrets.
- **Host compromise (`APP_KEY` + DB)**: Because TOTP codes are generated server-side inside Livewire islands so secrets never travel over the wire to browser JavaScript, the server holds `APP_KEY` in memory/environment. An attacker with full read access to **both** the running application environment (`APP_KEY`) and the database can decrypt secrets. Keep host SSH access, backups, and `.env` permissions (`0600`) tightly restricted.
- **Zero plaintext exposure in UI & Admin**:
  - `Authenticator` marks `secret` in `#[Hidden(['secret'])]` and redacts `toArray()` / `jsonSerialize()`.
  - Admin authenticator queries use `AuthenticatorBuilder::withoutSecrets()`, which omits the `secret` column at the SQL `SELECT` level. Admins can edit issuer/account labels or delete entries, never read secrets or codes.
  - Exceptions and logs strip `secret`, `uri`, and `payload` inputs.

## Key Backup & Rotation

1. **Back up `APP_KEY` offline**: Losing `APP_KEY` permanently renders all encrypted TOTP secrets unrecoverable.
2. **Rotating `APP_KEY`**:
   - Prepend the old key to `APP_PREVIOUS_KEYS` in `.env` (comma-separated).
   - Set the new `APP_KEY` (`php artisan key:generate --show`).
   - Run `php artisan vaulta:check` to confirm every stored authenticator decrypts cleanly.

## Authentication, Rate Limiting & Lockouts

- **Account protection**: Users can protect their Vaulta login with WebAuthn **Passkeys** (`laravel/passkeys`) and/or Fortify **TOTP Two-Factor Authentication** with single-use recovery codes.
- **Sign-in throttling & lockout**:
  - Per `email|ip` rate limiter (`5` attempts/minute) plus global IP limiter (`30` attempts/minute).
  - After `VAULTA_SIGN_IN_LOCKOUT_ATTEMPTS` (default `10`) failed attempts within 15 minutes, `RecordFailedSignIn` sets `users.sign_in_locked_until` for `VAULTA_SIGN_IN_LOCKOUT_MINUTES` (default `15`).
- **Blocked accounts**: `EnsureUserIsNotBlocked` runs on every authenticated web request, signing out and invalidating sessions immediately when an administrator blocks an account.
- **Session management**: With `SESSION_DRIVER=database`, users and administrators can inspect active sessions by IP/User-Agent and revoke individual devices (addressed via HMAC-SHA-256 session tokens rather than raw session IDs).

## Content Security Policy & HTTP Headers

`App\Http\Middleware\SecurityHeaders` attaches a per-request CSP nonce (`Vite::useCspNonce()`) and strict headers on every web response:

- `Content-Security-Policy`: `default-src 'self'`, `script-src 'self' 'nonce-...'` (`'unsafe-eval'` required by Alpine.js expressions), `font-src 'self' data:` (zero external font CDNs), `connect-src 'self'`, `object-src 'none'`, `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'`.
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: no-referrer`
- `Permissions-Policy: camera=(self), microphone=(), geolocation=(), interest-cohort=()` (`camera=(self)` allows optional local QR scanning when explicitly triggered by the user; QR image decoding runs locally in browser memory via `BarcodeDetector` and never uploads images).
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` when served over HTTPS.
