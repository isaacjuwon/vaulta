# Vaulta — Implementation Plan

Rebuild of `wellto.io` on **Laravel 13**, **Livewire 4.4 Single-File Components (SFC)**, **Flux UI 2.21**, **Actions**, **Custom Eloquent Builders**, and **Ferrite's 15-palette multi-theme system**.

## 1. Confirmed Decisions & Code-Style Rules

- **Scope**: Full `wellto.io` source parity (Vault CRUD, search, favorites, pagination, sort, `otpauth://` + QR image import, Account Profile/Avatar/Password/2FA/Passkeys/Active Sessions, Admin suite) + SPEC `TagColor` colour tags + Ferrite's 15 palettes + View Transitions + Security hardening.
- **License**: **AGPL-3.0-or-later** (`LICENSE` + `composer.json`), matching Ferrite.
- **Auth & 2FA Backend**: Retain Laravel Fortify (`laravel/fortify` + `@laravel/passkeys`) routes for login, registration, 2FA, and passkeys, hardened with `wellto.io`'s blocked-user check, 12-char mixed-case/number password defaults, IP/account lockout (`sign_in_locked_until`), and `config('vaulta.registration')` open/closed switch. All interactive pages (`Vault`, `Settings`, `Admin`) are **Livewire 4 SFCs**.
- **Fonts**: All 18 font families bundled as self-hosted `.woff2` files in `resources/fonts/{family}/` with license files, registered via `local()` in `vite.config.js` (zero external CDN requests at build or runtime).
- **Strict PHP Conventions (Enforced across all files & Architecture Tests)**:
  - `declare(strict_types=1);` in every PHP file and SFC header.
  - **Imported namespaces always** — never inline `\Fully\Qualified\ClassName` in code or Blade PHP blocks; import via `use` statements at the top of the file.
  - **Strict named arguments** — use named arguments on multi-parameter / ambiguous / boolean-flag / framework attribute / action `handle(...)` / DTO / helper calls for clarity and safety.
  - **PHP 8 Attributes everywhere applicable**:
    - Eloquent Models: `#[Table]`, `#[Fillable]`, `#[Hidden]`, `#[UseEloquentBuilder]`, `#[UsePolicy]`, `#[UseFactory]`
    - Console Commands: `#[Signature]`, `#[Description]`
    - Livewire 4 SFCs: `#[Layout]`, `#[Title]`, `#[Computed]`, `#[Locked]`, `#[Url]`, `#[Validate]`, `#[Transition]`, `#[On]`, `#[Async]`
    - PHP Core: `#[Override]` on overridden parent/interface methods
  - **Reference Isolation**: `ferrite/` and `wellto.io/` are read-only and excluded in `.gitignore`, `pint.json`, `phpstan.neon`, and `phpunit.xml`.

---

## 2. Phase-by-Phase Execution Plan

### Phase 0: Recon, Inventory & Spec Verification *(Completed)*
- [x] Full `wellto.io` source inventory & schema reconciliation → [`docs/parity.md`](../docs/parity.md)
- [x] Full `ferrite` 15-theme token & font inventory → [`docs/themes-inventory.md`](../docs/themes-inventory.md)
- [x] Verified Laravel 13.39 `#[UseEloquentBuilder]`, Livewire 4.4.8 `$wire.$island('codes').refreshCodes()`, and `laravel-vite-plugin` `local()` font provider
- [x] Resolved all open questions (`SPEC.md` §0.3 & §12)

### Phase 1: Scaffold, Tooling & Architecture Gates
1. Initialize Git repository (`git init`) and update `.gitignore` to exclude `/ferrite` and `/wellto.io`.
2. Add `AGPL-3.0-or-later` `LICENSE` file and update `composer.json` (`license`, scripts: `setup`, `dev`, `lint`, `lint:check`, `analyse`, `contrast`, `test`), `config/vaulta.php`, and `config/livewire.php`.
3. Configure `pint.json` (exclude `ferrite`, `wellto.io`), `phpstan.neon` (**Larastan level 8**, zero baseline, exclude `ferrite`, `wellto.io`), and `.github/workflows/tests.yml` (PHP 8.3 & 8.5 matrix + `composer audit` + `npm audit`).
4. Commit **Architecture Tests first** (`tests/Architecture/ArchitectureTest.php`):
   - `App\Actions` (excluding Fortify contract adapters where required) are `final readonly`, suffix-free verb names, have `handle()`, and never depend on `Livewire\`.
   - `App\Support\Totp` is pure PHP (`final`, no `Illuminate\Database` or `App\Models` imports).
   - `App\Models` use `#[UseEloquentBuilder]`.
   - Strict types declared across `app/`.
5. **Phase 1 Gate**: `composer lint:check`, `composer analyse`, `php artisan test --compact` green; tagged commit.

### Phase 2: Domain Core (TDD, Zero UI)
1. **`App\Support\Totp`** (`Clock`, `SystemClock`, `FrozenClock`, `Base32`, `TotpCode`, `Totp`, `OtpAuthUri`):
   - Write `tests/Unit/Totp/TotpTest.php` (RFC 6238 SHA-1 vectors: T=59 → `287082`, T=1111111109 → `081804`, T=1234567890 → `005924`, T=2000000000 → `279037`, Base32 normalisation, min-16 validation, `otpauth://totp/` parser).
2. **Enums** (`App\Enums\Palette` with 15 TitleCase cases, `isDark()`, `label()`, `description()`, `icon()`, `lightValues()`, `default()`; `App\Enums\TagColor` with `Zinc`, `Red`, `Amber`, `Emerald`, `Sky`, `Violet`, `Pink`, `label()`, `dotClass()`, `badgeClass()`).
3. **Database Migrations, Models, Factories & Policies**:
   - Update `users` migration / add Vaulta columns (`palette`, `avatar`, `is_admin`, `is_blocked`, `blocked_at`, `sign_in_locked_until`) + `sessions` table; create `authenticators` migration (`user_id`, `issuer`, `account`, `secret`, `color`, `is_favorite`, composite index `(user_id, is_favorite, issuer)`).
   - `User` model (`#[UseEloquentBuilder(UserBuilder::class)]`, `#[Fillable]`, `#[Hidden]`, casts for `palette` → `Palette`, booleans, datetimes) + `UserFactory` states (`admin()`, `blocked()`, `withPalette()`).
   - `Authenticator` model (`#[Table('authenticators')]`, `#[UseEloquentBuilder(AuthenticatorBuilder::class)]`, `#[UsePolicy(AuthenticatorPolicy::class)]`, `#[UseFactory(AuthenticatorFactory::class)]`, `#[Fillable]`, `#[Hidden(['secret'])]`, encrypted `secret` cast, `TagColor` cast).
4. **Custom Eloquent Builders**:
   - `AuthenticatorBuilder`: `ownedBy(User $user)`, `favoritesOnly()`, `matching(string $term)` (with LIKE `%_\` escaping on `issuer` and `account` only), `coloured(TagColor $color)`, `sortedBy(string $sort)` (`issuer`, `recent`, `favorites`), `withoutSecrets()`.
   - `UserBuilder`: `activeAdmins()`, `blocked()`, `matching(string $term)`.
   - Unit/Feature tests for builder vocabulary and LIKE wildcard escaping.
5. **Policies**: `AuthenticatorPolicy` (`view`, `update`, `delete` → `$user->is($authenticator->user)`).
6. **Domain Actions** (`final readonly`, constructor-promoted dependencies, `handle(...)` with strict named calls in callers):
   - `Auth`: `RegisterUser`, `SignOutUser`, `RecordFailedSignIn`
   - `Entries`: `AddEntry`, `ParseOtpAuthUri`, `RemoveEntry`, `SetEntryFavorite`, `SetEntryColor`
   - `Totp`: `GenerateEntryCodes` (sole place decrypting `Authenticator::$secret` for display)
   - `Appearance`: `ChangePalette`
   - `Settings`: `UpdateUserProfile`, `UpdateUserAvatar`, `RemoveUserAvatar`, `UpdateUserPassword`, `RevokeUserSession`, `RevokeOtherUserSessions`, `DeleteUserAccount`
   - `Admin`: `CreateManagedUser`, `UpdateManagedUser`, `BlockManagedUser`, `UnblockManagedUser`, `DeleteManagedUser`, `UpdateAuthenticatorMetadata`, `DeleteManagedAuthenticator`, `RevokeManagedSession`
7. **Console Commands** (`#[Signature]`, `#[Description]`):
   - `vaulta:check` (`CheckVaultCommand`: verifies every stored `Authenticator` secret decrypts cleanly under current `APP_KEY` / `APP_PREVIOUS_KEYS`).
   - `vaulta:grant-admin {email}` (`GrantAdminCommand` with `keyring:grant-admin` alias for parity).
   - `vaulta:import-wellto {path}` (`ImportWelltoCommand`: optional zero-loss SQLite importer for `wellto.io` databases sharing `APP_KEY`).
8. **Phase 2 Gate**: Parity rows P4–P6, P8–P10, P12–P13, P16, P18–P20 green at action/unit level; Pint + Larastan level 8 + Arch tests clean.

### Phase 3: Ferrite Multi-Theme System & Self-Hosted Fonts
1. **Self-Hosted WOFF2 Fonts**: Populate `resources/fonts/{family}/` with `.woff2` files + `OFL.txt` / `LICENSE.txt` for all 18 families; configure `local()` font definitions in `vite.config.js` (preloading JetBrains Mono 400/600 only).
2. **Modular Theme Stylesheets**:
   - `resources/css/themes/_contract.css`: dialog color inheritance, `.cozy-bg` base, `.totp-bar` / `.totp-fill` base, typography `--font-ui` & `font-size-adjust`, fixed overlay defaults with stable `view-transition-name`, `prefers-reduced-motion` rules.
   - All 15 palette files in `resources/css/themes/{id}.css`: `ferrite`, `ferrite-light`, `mac`, `desk95`, `zine`, `bubblegum`, `memphis`, `nokia`, `amber`, `phosphor`, `commodore`, `miami`, `amiga`, `wallstreet`, `lotus` — scoped so `[data-palette='{id}']` works both on `<html>` and on live preview cards.
   - Import into `resources/css/app.css`.
3. **Zero-FOUC Layouts & Head Partial**:
   - Server-rendered `<html data-palette="..." @class(['dark' => ...])>` on authenticated requests.
   - CSP-nonce-aware inline `<script nonce="{{ Vite::cspNonce() }}">` in `resources/views/partials/head.blade.php` syncing `localStorage['vaulta.palette']` against `Palette::lightValues()` before first paint.
4. **Live Picker Component & Page**:
   - `resources/views/components/settings/⚡palette-picker.blade.php` + `resources/views/pages/settings/⚡appearance.blade.php` rendering 15 live CSS preview cards (mini surface + heading + accent badge + `.totp-bar` sample scoped with `data-palette` and `.dark`).
5. **Documentation & Automated Theme Audits**:
   - `docs/themes.md` (contract + 4-step add-a-theme guide).
   - `scripts/contrast.php` wired to `composer contrast` (WCAG contrast verifier across all 15 palettes with explicit documented period-aesthetic exceptions).
   - `tests/Feature/Settings/AppearanceTest.php` & `tests/Unit/Themes/ThemeContractTest.php` (enum ↔ CSS 1:1 parity, `color-scheme` parity, picker rendering, persistence, invalid rejection).
6. **Phase 3 Gate**: `npm run build`, `composer contrast`, Pint, Larastan, and all theme tests green.

### Phase 4: Livewire 4 SFC UI & Auth/Settings/Admin Parity
1. **Layouts & Navigation**:
   - `layouts::app` (sidebar with Workspace All/Favorites counts, Settings links including Appearance & Sessions, conditional Administration nav, user avatar/initials footer, sign-out button) and `layouts::auth` / `welcome` landing page + `/sitemap.xml`.
2. **Hardened Fortify Auth**:
   - Wire `FortifyServiceProvider` to enforce `Password::min(12)->max(72)->mixedCase()->numbers()`, `EnsureUserIsNotBlocked`, `sign_in_locked_until` 15-minute lockout after 10 failures, registration open/closed toggle (`config('vaulta.registration')`), and redirect home to `/account/vault`.
   - Ensure Lucide custom Flux icons (`terminal`, `gamepad-2`, `save`, `shield-check`, `key-round`, `smartphone`) are present in `resources/views/flux/icon/`.
3. **Vault SFCs**:
   - `pages::vault.index` (`resources/views/pages/vault/⚡index.blade.php`)
   - `vault.entry-list` (`resources/views/components/vault/⚡entry-list.blade.php`):
     - `#[Url(as: 'q', history: false)]` search (`⌘K` + `?focus=search`), `#[Url]` filter (`All accounts` / `Favorites`), colour tag filter (`#[Url(as: 'color')]`), sort toggle (`issuer` / `recent` / `favorites`), pagination (`12, 24, 48, 96`).
     - `@island(name: 'codes')` rendering 6-digit codes via `GenerateEntryCodes`, server-time offset Alpine countdown (`data-server-time`, `data-period="30"`), exact 30-second boundary trigger via `$wire.$island('codes').refreshCodes()`, `visibilitychange` immediate refresh, and `#[Transition(skip: true)]` on `refreshCodes()`.
     - Alpine clipboard copy with `navigator.clipboard.writeText()` + `<textarea>` fallback + polite `aria-live` status & micro-feedback.
     - Card colour-tag selector (`SetEntryColor`), `aria-pressed` favorite star (`SetEntryFavorite`), confirmed removal (`RemoveEntry`), stat cards, and 4 contextual empty states.
   - `vault.add-entry` (`resources/views/components/vault/⚡add-entry.blade.php`):
     - Flux modal (`?add=1` auto-open), browser `BarcodeDetector` QR image scanner, `otpauth://totp/` link parser (`ParseOtpAuthUri`), manual Base32 form + optional `TagColor` selector, 10/min rate limit, focus restoration.
4. **Settings SFCs**:
   - `pages::settings.profile` (name, email, client-resized WebP/PNG/JPEG avatar upload & remove, account deletion).
   - `pages::settings.security` (password update rotating `remember_token` & revoking other DB sessions, Fortify 2FA QR modal + recovery codes, WebAuthn passkeys).
   - `pages::settings.sessions` (active DB sessions list, single-device & all-other-devices HMAC revocation).
   - `pages::settings.appearance` (live 15-palette picker).
5. **Admin Suite SFCs** (guarded by `auth`, `not-blocked`, `admin` middleware + `boot()` defense-in-depth):
   - `pages::admin.dashboard`, `pages::admin.users`, `pages::admin.authenticators` (uses `withoutSecrets()`), `pages::admin.sessions`.
6. **Phase 4 Gate**: Comprehensive `Livewire::test()` feature suite for every component action (success, validation, authorization, cross-user 404).

### Phase 5: View Transitions & Motion
1. Configure `::view-transition-*` rules in `resources/css/themes/_contract.css` inside `@media (prefers-reduced-motion: no-preference)`:
   - `wire:transition="entry-card"` (match-element per card via unique transition naming / list morphing) & `wire:transition="entry-list"` (120ms container fade on search).
   - `#[Transition(skip: true)]` verified on `refreshCodes()`.
   - `wire:transition="modal"` on add-entry & confirmation modals.
   - Directional settings tab transitions using `#[Transition(type: 'forward'|'backward')]` and `html:active-view-transition-type(...)` with graceful cross-fade fallback.
   - `document.startViewTransition()` around palette switch in `settings.palette-picker` (180ms root cross-fade, skipped when reduced motion is active).
   - Stable `view-transition-name` on `nokia`, `miami`, and `commodore` fixed pseudo-element overlays so they never flicker.

### Phase 6: Security Hardening & Final Verification
1. **`SecurityHeaders` Middleware**: Global middleware applying `Vite::useCspNonce()`, strict CSP (`script-src 'self' 'nonce-...' 'strict-dynamic'`, `object-src 'none'`, `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'`), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, HSTS on HTTPS, and `Cache-Control: private, no-store` for authenticated responses; Livewire CSP-compatible script route/nonce wiring.
2. **Snapshot Secret-Leakage Test**: Feature test asserting serialized Livewire snapshot JSON on `vault.index` and `vault.entry-list` never contains the raw or encrypted Base32 `secret`.
3. **Documentation**: Write [`docs/security.md`](../docs/security.md) (honest server-side `APP_KEY` threat model, `APP_PREVIOUS_KEYS` rotation procedure, `vaulta:check` usage) and update [`README.md`](../README.md) (AGPL-3.0-or-later notice, setup, key backup, registration mode, themes).
4. **Final Quality Sweep**:
   - Update [`docs/parity.md`](../docs/parity.md) with all rows `Green` and linked test files.
   - Run `vendor/bin/pint --format agent`, `composer analyse` (Larastan level 8), `composer contrast`, `npm run build`, and full Pest suite.
