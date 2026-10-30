# Vaulta — Parity Matrix & Source Inventory

Reconciled from `wellto.io/` source (`app/`, `routes/`, `resources/`, `database/`, `tests/`) and `SPEC (1).md` §11.
Scope confirmed: **Full wellto.io source parity + SPEC color tags**, retaining **Fortify Auth/2FA/Passkeys** routes, built on **Livewire 4 SFC + Actions + Custom Eloquent Builders**, licensed **AGPL-3.0-or-later**.

## 1. Schema Reconciliation (`wellto.io` vs. Vaulta)

| Table / Column | `wellto.io` Source | Vaulta Target | Notes |
|---|---|---|---|
| Vault entries table | `authenticators` | `authenticators` | Kept identical to `wellto.io` (`#[Table('authenticators')]` on `Authenticator` model) so SQLite data migration is zero-transform. |
| `authenticators.id` | `id` | `id` | Auto-increment PK |
| `authenticators.user_id` | `foreignId('user_id')->constrained()->cascadeOnDelete()` | Same + composite index `(user_id, is_favorite, issuer)` | Enforced via `AuthenticatorBuilder::ownedBy()` |
| `authenticators.issuer` | `string('issuer')` | `string('issuer')` | Max 100 chars |
| `authenticators.account` | `string('account')` | `string('account')` | Kept as `account` (not `account_label`) for schema parity; max 180 chars |
| `authenticators.secret` | `text('secret')`, `encrypted` cast, `#[Hidden]` | `text('secret')`, `encrypted` cast, `#[Hidden(['secret'])]` | RFC 6238 SHA-1 / 6 digits / 30s constants in `Totp` |
| `authenticators.color` | `string('color', 20)->default('zinc')` | `string('color', 20)->default('zinc')`, cast to `TagColor` | `zinc` (default/none), `red`, `amber`, `emerald`, `sky`, `violet`, `pink` |
| `authenticators.is_favorite` | `boolean('is_favorite')->default(false)` | `boolean('is_favorite')->default(false)` | Boolean cast |
| `users.palette` | *(not present)* | `string('palette', 24)->default('ferrite')`, cast to `Palette` | Ferrite 15-theme persistence |
| `users.avatar` | `longText('avatar')->nullable()` | `longText('avatar')->nullable()` | Validated WebP/PNG/JPEG data URL ≤1 MB |
| `users.is_admin` | `boolean('is_admin')->default(false)` | `boolean('is_admin')->default(false)` | Admin guard (`EnsureUserIsAdmin`) |
| `users.is_blocked` / `blocked_at` | `boolean('is_blocked')->default(false)`, `timestamp('blocked_at')->nullable()` | Same | Enforced in Fortify auth pipeline + `EnsureUserIsNotBlocked` middleware |
| `users.sign_in_locked_until` | `timestamp('sign_in_locked_until')->nullable()` | `timestamp('sign_in_locked_until')->nullable()` | 15-minute lockout after 10 failed sign-ins in 15 min |
| `users` 2FA & Passkeys | Custom 2FA columns | Fortify `two_factor_*` columns + `passkeys` table | Provided by starter kit migrations |
| `sessions` | Default Laravel DB sessions table | Default Laravel DB sessions table | Added to migration for active-session management |

## 2. Route & URL Inventory

| Method | URI | Route Name | Handler | Parity Source |
|---|---|---|---|---|
| `GET` | `/` | `home` | Redirects authenticated users to `vault`, renders landing `welcome` for guests | `wellto.io` |
| `GET` | `/sitemap.xml` | `sitemap` | `sitemap` XML view | `wellto.io` |
| `GET/POST` | `/login`, `/register`, `/logout`, `/two-factor-challenge`, `/forgot-password`, `/reset-password`, `/user/*`, `/passkeys/*` | Fortify standard names | Laravel Fortify + Passkeys + themed auth views | Starter kit + `wellto.io` auth/lockout parity |
| `GET` | `/account/vault` | `vault` | `Route::livewire('/account/vault', 'pages::vault.index')` | `wellto.io` primary vault URL |
| `GET` | `/vault`, `/dashboard` | `dashboard` (alias) | Redirect to `route('vault')` preserving query string | `wellto.io` legacy `/vault` + starter-kit `/dashboard` |
| `GET` | `/settings/profile` | `profile.edit` | `pages::settings.profile` (SFC: name, email, avatar upload/remove, delete account) | Starter kit + `wellto.io` profile/avatar |
| `GET` | `/settings/security` | `security.edit` | `pages::settings.security` (SFC: password rotation + session revocation, 2FA, passkeys) | Starter kit + `wellto.io` security |
| `GET` | `/settings/sessions` | `sessions.edit` | `pages::settings.sessions` (SFC: DB session list, revoke single/other devices) | `wellto.io` `/account/sessions` |
| `GET` | `/settings/appearance` | `appearance.edit` | `pages::settings.appearance` (SFC: `settings.palette-picker` 15 live preview cards) | Ferrite + SPEC §6.1 |
| `GET` | `/account/{section?}` | `account.settings` | Redirects `profile`→`profile.edit`, `security`→`security.edit`, `sessions`→`sessions.edit`, `appearance`→`appearance.edit` | `wellto.io` URL compatibility |
| `GET` | `/admin/dashboard` | `admin.dashboard` | `pages::admin.dashboard` (SFC) | `wellto.io` Admin overview |
| `GET` | `/admin/users` | `admin.users` | `pages::admin.users` (SFC) | `wellto.io` Admin user CRUD/block |
| `GET` | `/admin/authenticators` | `admin.authenticators` | `pages::admin.authenticators` (SFC) | `wellto.io` Admin entry metadata |
| `GET` | `/admin/sessions` | `admin.sessions` | `pages::admin.sessions` (SFC) | `wellto.io` Admin session list/revoke |

## 3. Parity Matrix

| ID | Feature / Behaviour | Vaulta Artefact | Proving Test | Status |
|---|---|---|---|---|
| **P1** | Registration (open/closed config switch, password rules, rate limit) | Fortify `CreateNewUser`, `RegisterUser` action, `config/vaulta.php` | `tests/Feature/Auth/RegistrationTest.php` | Implemented |
| **P2** | Sign-in (remember me, IP/email throttle, 10-fail 15m account lock, blocked check) | `FortifyServiceProvider`, `EnsureUserIsNotBlocked` | `tests/Feature/Auth/AuthenticationTest.php`, `BlockedAccountTest.php` | Implemented |
| **P3** | Sign-out (invalidate session, regenerate token, keep guest palette in localStorage) | `SignOutUser` / Fortify logout | `tests/Feature/Auth/AuthenticationTest.php` | Implemented |
| **P4** | Per-account vault isolation (cross-user returns 404 on every action) | `AuthenticatorBuilder::ownedBy`, `AuthenticatorPolicy` | `tests/Feature/Vault/VaultIsolationTest.php` | Implemented |
| **P5** | Encrypted secrets at rest & never serialized in Livewire snapshots | `Authenticator` (`encrypted` cast, `#[Hidden]`) | `tests/Feature/Vault/VaultSecurityTest.php` | Implemented |
| **P6** | RFC 6238 six-digit SHA-1 30s TOTP codes with injected `Clock` | `Base32`, `Totp`, `TotpCode`, `GenerateEntryCodes` | `tests/Unit/Totp/TotpTest.php` | Implemented |
| **P7** | 30-second boundary island refresh (`$wire.$island('codes').refreshCodes()`) + server-offset Alpine countdown + visibility refresh | `vault.entry-list` (`@island(name: 'codes')`), `#[Transition(skip: true)]` | `tests/Feature/Vault/EntryListTest.php` | Implemented |
| **P8** | Search (`issuer` & `account`, LIKE-escaped `%_\`, `#[Url(as: 'q')]`, `⌘K` & `?focus=search`) | `AuthenticatorBuilder::matching`, `vault.entry-list` | `tests/Feature/Vault/EntryListTest.php` | Implemented |
| **P9** | Colour tags (`TagColor` enum, assign in add modal & card menu, filter by colour) | `TagColor`, `SetEntryColor`, `AuthenticatorBuilder::coloured`, `ui.tag-dot` | `tests/Feature/Vault/EntryColorTest.php` | Implemented |
| **P10** | Favorites (`SetEntryFavorite` idempotent action, All/Favorites filter, A–Z / Recent / Favorites-first sort, pagination 12/24/48/96) | `SetEntryFavorite`, `AuthenticatorBuilder`, `vault.entry-list` | `tests/Feature/Vault/EntryFavoriteTest.php` | Implemented |
| **P11** | Copy to clipboard (Alpine `navigator.clipboard` + textarea fallback + inline/toast feedback, zero server round-trip) | `vault.entry-list` script | `tests/Feature/Vault/EntryListTest.php` | Implemented |
| **P12** | Entry removal with confirmation (`RemoveEntry`, cross-user 404) | `RemoveEntry`, `vault.entry-list` | `tests/Feature/Vault/EntryRemovalTest.php` | Implemented |
| **P13** | Add entry: manual Base32 (≥16 chars, spaces/dashes stripped) + `otpauth://totp/` URI parser + client `BarcodeDetector` QR image import + 10/min rate limit | `ParseOtpAuthUri`, `AddEntry`, `vault.add-entry` | `tests/Feature/Vault/AddEntryTest.php` | Implemented |
| **P14** | Responsive dashboard, stat cards, 4 contextual empty states | `pages::vault.index`, `vault.entry-list` | `tests/Feature/Vault/VaultPageTest.php` | Implemented |
| **P15** | Flux UI forms, buttons, modals (`dialog` inherits theme color) | Flux components + `_contract.css` | Component feature tests | Implemented |
| **P16** | Env/setup parity (`composer setup`, `lint`, `analyse`, `contrast`, `test`, `vaulta:check`, `vaulta:grant-admin`) | `composer.json`, `CheckVaultCommand`, `GrantAdminCommand` | `tests/Feature/Console/CommandsTest.php` | Implemented |
| **P17** | Account 2FA (TOTP QR + single-use recovery codes + replay protection) & Passkeys | Fortify 2FA + Passkeys (`pages::settings.security`) | `tests/Feature/Auth/TwoFactorChallengeTest.php`, `SecurityTest.php` | Implemented |
| **P18** | Account Profile & Avatar (WebP/PNG/JPEG ≤1MB data-URL validation & removal) + Password rotation revoking other sessions | `UpdateUserProfile`, `UpdateUserAvatar`, `RemoveUserAvatar`, `pages::settings.profile` | `tests/Feature/Settings/ProfileUpdateTest.php` | Implemented |
| **P19** | Active DB Sessions list & HMAC-token revocation (single device & all other devices) | `RevokeUserSession`, `RevokeOtherUserSessions`, `pages::settings.sessions` | `tests/Feature/Settings/SessionsTest.php` | Implemented |
| **P20** | Admin suite (Overview, Users CRUD/block/unblock/last-admin & self guards, Authenticators metadata edit/delete without secrets, Sessions) | `app/Actions/Admin/*`, `pages::admin.*`, `UserBuilder` | `tests/Feature/Admin/*.php` | Implemented |
| **P21** | Multi-theme system: 15 Ferrite palettes, self-hosted WOFF2 fonts, live CSS preview cards, guest `localStorage` zero-FOUC, WCAG contrast script | `Palette`, `ChangePalette`, `resources/css/themes/*`, `settings.palette-picker` | `tests/Feature/Settings/AppearanceTest.php`, `ThemeContractTest.php` | Implemented |
| **P22** | View Transitions (`wire:transition`, `#[Transition]`, directional settings nav, palette cross-fade, `prefers-reduced-motion` gate) | `resources/css/themes/_contract.css`, SFC attributes | `tests/Feature/Settings/AppearanceTest.php` | Implemented |
| **P23** | Security headers (CSP with Vite nonce, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS, auth `no-store`) | `SecurityHeaders` middleware | `tests/Feature/Security/SecurityHeadersTest.php` | Implemented |
