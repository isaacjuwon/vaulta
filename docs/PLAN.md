# Vaulta: Plan

Rebuild of wellto.io on Livewire 4 SFC + actions + custom builders, with Ferrite's multi-theme system.
Normative detail lives in `SPEC.md`; this file is the order of work, exit criteria and risks.

## Ground rules

- **Parity first, then polish.** No feature work outside the parity matrix (`SPEC.md` §11) until every row is green.
- **Read before write.** Phase 0 produces no app code.
- **Tests alongside behaviour.** Domain core is built and proven before any UI exists.
- **Each phase ends with:** Pint clean, Larastan clean, tests green, parity matrix updated, one tagged commit.
- **References stay read-only** in `vaulta/ferrite/` and `vaulta/wellto.io/` and are excluded from Composer autoload, Pint, Larastan, Pest and Git.

## Phase 0: Recon and inventory (no app code)

Closes the gaps listed in `SPEC.md` §0.1.

| Task | Output |
|---|---|
| Read wellto `app/`, `routes/`, `resources/views`, `database/migrations`, `tests/` | `docs/parity.md`: full function inventory, extending the matrix; schema reconciliation; route and URL list |
| Confirm whether starter-kit 2FA/profile screens exist (`bacon/bacon-qr-code` is present) | Matrix rows added or explicitly ruled out |
| Read Ferrite `resources/css/app.css`, `vite.config.js`, `partials/head.blade.php`, `app/Models/User.php`, appearance page, `app/Actions/Nodes/*` | `docs/themes-inventory.md`: exact token values per palette, the zinc steps each light theme overrides, overlay z-index values, `phosphor` fonts, `.cozy-bg` recipes |
| Resolve licence question (`SPEC.md` §0.3) | Written decision at top of `README.md` |
| Check Livewire 4.4 docs for: island JS refresh API, `@island` options, custom namespace config key, `#[UseEloquentBuilder]` name in Laravel 13 | Every `(verify)` in the spec turned into a confirmed statement or a changed one |
| Answer open questions (`SPEC.md` §12) | Decisions logged |

**Exit:** parity matrix complete; zero `(verify)` left in SPEC; licence path chosen.

## Phase 1: Scaffold and tooling

- Fresh Laravel 13, PHP ^8.3, `livewire/livewire:^4.4`, `livewire/flux:^2.20`, Tailwind v4, Vite.
- Pest, Pint (with `pint.json`), Larastan level 8, GitHub Actions (PHP 8.3 and 8.5; optional MySQL job).
- `config/livewire.php` per `SPEC.md` §6.2; layouts directory; SQLite default.
- Composer scripts: `setup`, `dev`, `test`, `lint`, `analyse`, `contrast` (stub).
- Architecture tests committed first, failing for nothing yet, so rules exist before code.
- Decide Pest browser testing (open question 5).

**Exit:** blank app boots; `composer setup && composer test` passes on a clean clone; CI green.

## Phase 2: Domain core (no UI)

Build in this order, each with tests written first:

1. `Support/Totp`: `Base32`, `Clock`, `Totp`, `TotpCode`. First test: RFC 6238 vectors.
2. Migrations, `User`, `Entry` (encrypted cast, `$hidden`), factories.
3. `TagColor`, `Palette` enums.
4. `EntryBuilder` and its tests (including LIKE-escaping).
5. `EntryPolicy` and tests.
6. Actions: `RegisterUser`, `AddEntry`, `RemoveEntry`, `SetEntryFavorite`, `SetEntryColor`, `GenerateEntryCodes`, `ChangePalette`, `SignOutUser`.
7. Data migration script (optional) from wellto's SQLite schema, if user data must carry over. Requires the same `APP_KEY`.

**Exit:** parity rows P4, P5, P6, P8, P9, P10, P12, P13 are green at action level; architecture tests pass; no Livewire imports in Actions or Support.

## Phase 3: Theming system

Order matters: tokens before components, so every component is built against real themes.

1. Licence-driven decision applied (port vs. reimplement).
2. `_contract.css` and `resources/css/themes/ferrite.css` (the default) first; get the base working end to end.
3. Remaining 14 palette files, one commit each, in this order: `ferrite-light`, `mac`, `desk95`, `zine`, `bubblegum`, `memphis`, `nokia`, `amber`, `phosphor`, `commodore`, `miami`, `amiga`, `wallstreet`, `lotus`. (Simple palettes first; overlay-heavy ones last.)
4. Self-hosted fonts, licence files, `@font-face` per theme.
5. Layouts: server-side `data-palette` and `dark` class; guest `localStorage` script with nonce support.
6. `.totp-bar` skin per theme (the upload-bar equivalent).
7. `settings.palette-picker` with live preview cards (`data-palette` on the card).
8. `docs/themes.md` for Vaulta: contract, add-a-theme procedure.
9. Theme tests and `composer contrast`.

**Exit:** all 15 palettes render the layouts, login page and picker; no flash of wrong theme for authed or guest; theme tests and contrast audit green (exceptions reviewed and recorded).

## Phase 4: Livewire SFC UI at parity

1. `layouts::app`, `layouts::guest`, shared `ui.*` Blade components.
2. `pages::auth.register` and `pages::auth.login` (rate limiting, remember me).
3. `pages::vault.index` with `vault.entry-list`: search, favorites, colour tags, remove with confirmation.
4. Codes island, Alpine countdown driven by server time, visibility refresh (`SPEC.md` §6.6).
5. `vault.add-entry` modal with form object.
6. Clipboard handler and fallback.
7. `pages::settings.appearance`.
8. Sign-out control.
9. Accessibility pass per `SPEC.md` §6.9.

**Exit:** parity rows P1 to P3, P7, P11, P14, P15 green; every public component action has success, validation and authorization tests; cross-user access returns 404 for every action.

## Phase 5: Transitions and motion

1. Base CSS: reduced-motion gate, shared easing and durations as custom properties.
2. Entry add/remove/reorder with `wire:transition="entry-card"`.
3. List container fade for search.
4. `#[Transition(skip: true)]` on `refreshCodes`; confirm no cross-fade on refresh.
5. Modal transition.
6. Directional transition for settings navigation using `#[Transition(type: …)]` and `:active-view-transition-type()`; verify the Firefox no-types fallback.
7. Palette-change cross-fade with `document.startViewTransition()`.
8. Overlay check for `nokia`, `miami` and `commodore` during all transitions.
9. Manual sweep in Chrome, Safari 18+, Firefox 144+, and with reduced motion on.

**Exit:** no jank at 60 fps on a mid-range laptop; reduced motion produces zero animation; unsupported-browser path verified.

## Phase 6: Hardening

- Rate limits (login, register, entry creation), lockout messaging.
- CSP with nonce, security headers, cookie flags (`SPEC.md` §9).
- `vaulta:check` command (every entry decrypts); key-rotation doc with `APP_PREVIOUS_KEYS`.
- `composer audit` and `npm audit` in CI.
- `docs/security.md`: threat model, limits of server-side encryption.
- Browser smoke suite and 15-palette screenshot sweep (if adopted).

**Exit:** headers verified by a test; no secret appears in any Livewire snapshot (test asserts the serialized component payload contains no secret or code material beyond the displayed codes); security doc reviewed.

## Phase 7: Release

- Complete `docs/parity.md` with all rows green and linked tests.
- README (install, key backup, registration setting, themes), CONTRIBUTING, licence file per decision.
- Remove `ferrite/` and `wellto.io/` reference folders (or move to a `references` branch).
- Tag `v0.1.0`.

**Optional after release, outside parity:** otpauth URI and QR import, entry editing if absent in wellto, export/import with passphrase, account 2FA and passkeys, `wire:sort` manual ordering, `@placeholder` skeletons for slow loads.

## Risk register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| AGPL obligations from copying Ferrite | High if copied | High | Decide in Phase 0 (`SPEC.md` §0.3); do not port a file before the decision |
| wellto has undocumented functions | Medium | High | Phase 0 inventory from source; matrix is the contract |
| Livewire 4 island refresh API differs from assumption | Medium | Medium | Verify in Phase 0; fallback hidden-button trigger designed in §6.6 |
| View Transitions + fixed overlays flicker | Medium | Low | Phase 5 step 8; assign stable names to overlays |
| Per-element `match-element` transitions fire too often | Medium | Medium | Skip on refresh, keep countdown outside transitions |
| Theme token drift (Flux changes zinc usage) | Low | Medium | Contract file, theme tests, Flux pinned to a minor |
| Decrypt failure after `APP_KEY` change | Medium | High | `vaulta:check`, rotation doc, backup warning in README and first-run notice |
| 15 themes × every new component = visual regressions | High | Medium | Screenshot sweep per palette in CI or release checklist |
| Client clock skew breaks countdown | Medium | Low | Server-time offset (`SPEC.md` §6.6) |
| Font licensing mistakes | Low | Medium | Licence files stored beside fonts; audit in Phase 3 |

## Working agreement for implementation

- Each task references a parity row or a plan item in its commit message.
- Anything discovered that contradicts `SPEC.md` is fixed in the spec first, then in code.
- No new dependency without a line in the plan stating why the in-house option is worse.
