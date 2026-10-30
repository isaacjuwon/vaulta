# Vaulta: Specification

Vaulta is a rebuild of **wellto.io** (a private, Ente Auth-inspired TOTP authenticator vault) on **Livewire 4 single-file components**, with an **actions pattern**, **custom Eloquent builders**, and the **multi-theme system extracted from Ferrite**.

Companion document: `PLAN.md` (phases, exit criteria, risks).

---

## 0. Provenance and assumptions

### 0.1 What was read

| Source | Read | Not readable |
|---|---|---|
| `zhoorta/ferrite` | README, `PLAN.md`, `docs/themes.md` | `resources/css/app.css`, `app/Actions/**`, `app/Models/User.php`, `vite.config.js`, partials (GitHub tree pages blocked) |
| `atmdevx/wellto.io` | README, `AGENTS.md`, `composer.json` | `app/**`, `routes/**`, `resources/**`, `database/**`, `tests/**` |
| Livewire 4 docs | Components, `wire:transition`, `#[Transition]`, `#[Layout]`, upgrade guide, release write-ups | Islands and `wire:sort` pages (secondary sources only) |

Everything in section 3 is sourced from those reads. Everything marked **(verify)** must be confirmed against the local copies in Phase 0 of the plan. Nothing in this spec claims knowledge of wellto's source code beyond what its README and `composer.json` show.

### 0.2 Interpretation decisions (change them if wrong)

| # | Ambiguity | Decision |
|---|---|---|
| A1 | "Custom Builders" | Custom **Eloquent query builders** (one per model that needs query vocabulary), registered with `#[UseEloquentBuilder]` **(verify attribute name in Laravel 13)**. |
| A2 | "Both projects available in the root folder of vaulta" | Vaulta is the app at the repo root. The two source projects sit in `vaulta/ferrite/` and `vaulta/wellto.io/` as **read-only references**, excluded from Composer autoload, Pint, PHPStan, Pest and Git. They are deleted once the parity matrix is green. |
| A3 | "Retain every function of wellto.io" | The parity matrix (section 11) is built from the README now and **completed from wellto's source in Phase 0**. Any function found in source but not in the README is added to the matrix and treated as mandatory. |
| A4 | "Replace the theming system with Ferrite including multitheme" | Port all 15 palettes and the mechanism. The mechanism is improved where Ferrite's own docs show duplication (section 7.2). |
| A5 | "Transition" | Livewire 4's `wire:transition` and `#[Transition]` (View Transitions API), not the Livewire 3 Alpine-based modifiers, which v4 removed. |

### 0.3 Licensing gate (must be resolved before porting any Ferrite file)

Ferrite is **AGPL-3.0-or-later**. Copying or closely adapting its CSS, PHP or docs makes Vaulta a derivative work that must be offered under AGPL-compatible terms. The wellto.io repository showed **no LICENSE file** in its root listing, so its terms are unknown (default copyright, all rights reserved). Decide before Phase 3:

1. Publish Vaulta as AGPL-3.0-or-later, or
2. Reimplement the theme *design* (the documented tokens, names, colours and fonts) from scratch without copying Ferrite source, or
3. Get written permission from the authors.

This is a legal question; this spec does not resolve it.

---

## 1. Goals and non-goals

### Goals

- Every wellto.io function preserved, proven by tests tied to the parity matrix.
- Livewire 4 idioms used for real: SFC, `Route::livewire()`, namespaces, islands, `wire:transition`, `#[Transition]`, `#[Computed]`, `#[Locked]`, `#[Url]`, `#[Async]` where it fits.
- Business logic in single-purpose action classes that are testable without Livewire.
- Query vocabulary in custom builders, not scattered `where` chains.
- All 15 Ferrite palettes, selectable per account, applied without flash.
- Code that passes strict static analysis and reads like one author wrote it.

### Non-goals (parity phase)

- New product features (QR import, backups, sharing, sync). Listed as optional in `PLAN.md` Phase 7 only.
- Native apps, browser extensions.
- Changing wellto's cryptographic model (server-side encryption at rest via Laravel's encrypted cast). See section 9 for its honest limits.

---

## 2. Stack

| Layer | Choice | Basis |
|---|---|---|
| PHP | ^8.3 | wellto `composer.json`; Ferrite recommends 8.5 |
| Framework | Laravel ^13 | wellto (`^13.17`), Ferrite (Laravel 13) |
| Reactivity | Livewire ^4.4 | wellto `composer.json` |
| UI kit | Flux UI ^2.20 | both projects |
| CSS | Tailwind CSS v4 | Ferrite (zinc tokens, `--radius-*`, `--shadow-*`, `--font-*`) |
| JS | Alpine.js (bundled with Livewire 4) | wellto |
| DB | SQLite default, MySQL/MariaDB compatible | both |
| Tests | Pest + `Livewire::test()` | Ferrite uses Pest; wellto uses PHPUnit 12 (**choice**: Pest, runs on PHPUnit 12) |
| Format / analyse | Pint, Larastan level 8 | Ferrite ships `pint.json` and `phpstan.neon`; level 8 is **our choice** |

Do not add an OTP library. wellto's `composer.json` has none, so TOTP is implemented in-house; it is about 40 lines of HMAC code (section 5.4). `bacon/bacon-qr-code` is in wellto's dependencies, which suggests starter-kit two-factor scaffolding **(verify in Phase 0 whether account 2FA exists; if so it joins the parity matrix)**.

---

## 3. Source analysis

### 3.1 wellto.io (from README and `composer.json`)

Functions stated by the README:

1. Registration, sign-in, sign-out.
2. Per-account vault isolation.
3. TOTP secrets encrypted at rest through Laravel's encrypted Eloquent cast.
4. RFC 6238 six-digit codes with automatic 30-second refresh.
5. Search.
6. Colour tags.
7. Favorites.
8. Copy-to-clipboard.
9. Account (entry) removal.
10. Responsive vault dashboard.
11. Flux UI forms, buttons and a modal.
12. Add entry from a Base32 setup key, minimum 16 characters.

Operational facts: SQLite by default, `APP_KEY` must be kept and backed up because it decrypts stored secrets; production notes mention HTTPS, restricted registration and rate limiting as deployer duties.

Repository conventions worth keeping (from `AGENTS.md`): descriptive names (`isRegisteredForDiscounts`, not `discount()`), constructor property promotion, explicit return types, TitleCase enum keys, curly braces always, PHPDoc with array shapes, factories for every model, named routes, feature tests over tinker scripts, Pint before finishing.

### 3.2 Ferrite theming system (from `docs/themes.md`)

**Mechanism**

- 15 themes: `ferrite` (default, dark), `ferrite-light`, and 13 vintage ones. Stored per account in `users.palette`, default `ferrite`.
- Palette list lives in `User::PALETTES`; light ones in `User::LIGHT_PALETTES`; `User::isDarkPalette()` decides. The same light list is **duplicated** in `partials/head.blade.php` and in the appearance page.
- Layouts put `class="dark"` on `<html>` unless the palette is light; `app.css` sets `color-scheme` to match.
- **Dark palettes redefine only the `zinc-700/800/900/950` tokens.** `ferrite` is the base in the `.dark` block; vintage dark themes are `.dark[data-palette='…']` overrides.
- **Light palettes** are scoped `html:not(.dark)[data-palette='…']` and redefine more of the scale (e.g. `nokia` sets `zinc-50` and `zinc-800`; `memphis` sets `zinc-300` and `zinc-100`).
- Signed-in pages get `data-palette` from the server. Guest pages (sign-in, share links) use the last palette that browser saw, stored in `localStorage` (`ferrite.palette`) by an inline script in the head partial.
- Adding a palette = a CSS block + an entry in `User::PALETTES` + a card on the appearance page.

**Token and hook vocabulary a theme may set**

| Hook | Purpose |
|---|---|
| `--color-zinc-*` | Surface and text scale (Flux is built on zinc) |
| accent colour | Primary action colour; some themes put dark text on it |
| `--radius-*` | All 0 for square themes (`mac`, `amber`, `lotus`, …) |
| `--shadow-*` | Hard offset shadows (`mac`, `memphis`, `amiga`), glow (`amber`, `miami`), none (`zine`, `lotus`) |
| `--font-sans` / `--font-serif` / `--font-ui` | Text / headings / buttons, labels, menus, tabs, table heads, sidebar links (`--font-ui` inherits text font when unset) |
| `--font-ui-adjust` | A `font-size-adjust` value for fonts with a small x-height (`amber` 0.52, `commodore` 0.4, `nokia` 0.55) |
| `--crt-glow` | `r g b` triplet shared by `amber` and `phosphor` |
| `.cozy-bg` | Page background layer: dotted desktop, scanlines, grid, confetti SVG, glows, pinstripes |
| `html::before`, `html::after` | Fixed, `pointer-events: none` overlays: `nokia` status bar, `miami` pink-turquoise rule, `commodore` frame |
| `.upload-bar`, `.upload-fill` | Progress bar skin; every theme overrides it |
| `[data-flux-main]` | `memphis` veils the confetti with an 82% cream layer here |
| `--color-white` remap | Done by `amber` and `phosphor` |

**Gotchas Ferrite documents**

- `<dialog>` resets its text colour, so dialogs must inherit page colour. Keep that rule when styling modals.
- A new theme font must be registered in `vite.config.js` (Bunny Fonts).
- Dark `ferrite`: plain background, no glow or grain; text and headings in JetBrains Mono.

**The 15 palettes**

| id | mode | accent / identity | text font | heading + UI font |
|---|---|---|---|---|
| `ferrite` | dark | warm graphite, cream text, rust (tape oxide) | JetBrains Mono | JetBrains Mono |
| `ferrite-light` | light | warm paper white, rust `#b0502a` | JetBrains Mono | JetBrains Mono |
| `mac` | light | System 6/7: black on white, 1px black borders, hard shadows, dotted desktop, accent black | Public Sans | Pixelify Sans |
| `desk95` | light | silver, navy accent, raised and sunken bevels (box-shadow), navy block progress | Public Sans | Public Sans |
| `zine` | light | white page, blue underlined links, no shadow or rounding | Tinos | Courier Prime (buttons, labels) |
| `bubblegum` | light | pastel pink and lilac, hot pink `#d6338a`, big radii, pink-tinted shadows | Quicksand | Fredoka |
| `memphis` | light | cream with pastel confetti SVG, near-black borders, pink `#ff6fae`, hard shadows | Poppins | Poppins |
| `nokia` | light | pixel green `#28351f` on `#c8d6a4`, 3px pixel grid, status bar overlay, inverted selection | DotGothic16 | Jersey 10 (adjust 0.55) |
| `amber` | dark | amber phosphor `#ffb000`, scanlines, glow, square | IBM Plex Mono | VT323 (adjust 0.52) |
| `phosphor` | dark | green phosphor, shares the amber block, differs in colours and `--crt-glow` | **(verify)** | **(verify)** |
| `commodore` | dark | C64: dark blue, light blue text, yellow-green accent, `html::after` frame | DotGothic16 | Press Start 2P (adjust 0.4) |
| `miami` | dark | indigo `#15122f`, flamingo `#ff5fa2`, turquoise secondary, neon text-shadow, `html::before` rule | Outfit | Righteous |
| `amiga` | dark | Workbench 1.3: blue, white text, orange accent, black hard shadows | Share Tech Mono | Silkscreen |
| `wallstreet` | dark | navy pinstripes (opacity .025), gold `#d4af37`, small radii | Libre Franklin | Libre Franklin |
| `lotus` | dark | black DOS spreadsheet, worksheet grid, cyan accent, inverse-video selection, flat | IBM Plex Mono | IBM Plex Mono |

Light: `ferrite-light, mac, desk95, zine, bubblegum, memphis, nokia` (7). Dark: the other 8.

### 3.3 Livewire 4 facts this spec depends on

| Fact | Source |
|---|---|
| SFC is the default format: PHP + Blade in one file, `new class extends Component { … }`, optional `<style>` (scoped by default, `<style global>` opt-out) and `<script>` (no `@script` wrapper needed) | Livewire 4 components docs, release write-ups |
| SFC file path: `resources/views/components/post/⚡create.blade.php` is `post.create`; namespaced: `resources/views/pages/post/⚡create.blade.php` is `pages::post.create` | components docs |
| `Route::livewire('/dashboard', 'pages::dashboard')`, required for SFC/MFC full-page components | upgrade guide |
| Default layout config: `'component_layout' => 'layouts::app'` (renamed from `layout`); `lazy_placeholder` is now `component_placeholder` | upgrade guide |
| `#[Layout('layouts::x', ['title' => '…'])]` on the anonymous class: `new #[Layout('layouts::dashboard')] class extends Component {}` | `#[Layout]` docs |
| `wire:transition` uses the **View Transitions API**; v3 modifiers (`.opacity`, `.scale`, `.duration`) are removed | upgrade guide, `wire:transition` docs |
| `wire:transition` with no value assigns `view-transition-name: match-element`; `wire:transition="name"` uses that name; styled via `::view-transition-old/new/group(name)` | `wire:transition` docs |
| Transition *types*: `#[Transition(type: 'forward')]` on an action, or `$this->transition(type: …)` at runtime, targeted with `html:active-view-transition-type(forward)`; `#[Transition(skip: true)]` disables | `#[Transition]` docs |
| Browser support: Chrome/Edge 111+, Safari 18+; Firefox 144+ has basic transitions **without types**. Unsupported browsers update without animation | `wire:transition` docs |
| `wire:model` ignores child events by default (`.deep` restores); `wire:model.blur` is now `.live.blur`; component tags must be closed | upgrade guide |
| Islands: `@island(name: 'stats')` isolates a region's re-render; `wire:island="name"` targets one from a trigger | release write-ups |
| Also in v4: `#[Async]`, `wire:click.async`, `wire:sort`, `wire:intersect`, `wire:ref`, `.renderless`, `.preserve-scroll`, `@placeholder`, `$errors`, `$intercept`; `wire:scroll` is now `wire:navigate:scroll` | skill summaries, release write-ups |
| `php artisan livewire:convert` moves components between SFC, MFC and class formats; `make:livewire x --mfc` for multi-file | components docs |

Anything about islands beyond this table comes from secondary sources and is **(verify)** against `livewire.laravel.com/docs/4.x` during Phase 4.

---

## 4. Architecture

### 4.1 Repository layout

```
vaulta/
├─ ferrite/                       # reference, read-only, excluded from tooling
├─ wellto.io/                     # reference, read-only, excluded from tooling
├─ app/
│  ├─ Actions/
│  │  ├─ Auth/        RegisterUser, SignOutUser
│  │  ├─ Entries/     AddEntry, RemoveEntry, SetEntryFavorite, SetEntryColor
│  │  ├─ Appearance/  ChangePalette
│  │  └─ Totp/        GenerateEntryCodes
│  ├─ Enums/          Palette, TagColor
│  ├─ Http/           (thin: only what Livewire cannot own, e.g. sign-out POST)
│  ├─ Models/         User, Entry
│  │  └─ Builders/    EntryBuilder
│  ├─ Policies/       EntryPolicy
│  └─ Support/Totp/   Base32, Totp, TotpCode
├─ config/livewire.php
├─ database/{migrations,factories,seeders}
├─ resources/
│  ├─ css/
│  │  ├─ app.css                  # Tailwind, Flux, imports
│  │  └─ themes/                  # one file per palette + _contract.css
│  ├─ js/app.js
│  └─ views/
│     ├─ layouts/        app.blade.php, guest.blade.php        (layouts::)
│     ├─ pages/          auth/⚡login, auth/⚡register, vault/⚡index, settings/⚡appearance   (pages::)
│     ├─ components/     vault/⚡entry-list, vault/⚡add-entry, settings/⚡palette-picker, ui/*.blade.php
│     └─ partials/head.blade.php
├─ routes/web.php
├─ tests/{Unit,Feature,Architecture}
└─ docs/{themes.md, security.md, parity.md}
```

### 4.2 Layering rules (enforced by architecture tests)

1. **Components** (SFC) hold UI state, validation (Livewire form objects or `#[Validate]`), authorization calls, and call exactly one action per user intent. No query chains, no crypto, no business rules.
2. **Actions** are `final readonly` classes with constructor-injected dependencies and one public `handle()` method taking typed arguments. They contain the domain write, wrap multi-write work in a transaction, and know nothing about Livewire or HTTP.
3. **Builders** hold query vocabulary. Components and actions never write `->where('user_id', …)` against `Entry`.
4. **Models** hold casts, relations, and attribute accessors. No queries beyond relations.
5. **Support/Totp** is pure PHP: no facades, no models, no clock access except through an injected `Clock`.
6. **Policies** are the single authority on who may touch an `Entry`.

Authorization sequence (a **decision**, defensible either way): the component calls `$this->authorize()` at the HTTP boundary; actions receive already-authorised typed inputs. Defence in depth comes from `EntryBuilder::ownedBy()` being the only way components load entries, so an unauthorised id resolves to a 404 before authorization even runs.

Architecture tests (Pest `arch()`): actions are `final`, `readonly`, suffix-free verb names, depend on nothing in `Livewire\`; `Support\Totp` depends on nothing in `Illuminate\Database`; models are not used in `Support`; no `DB::` facade outside actions.

### 4.3 Code-quality contract ("no slop")

- `declare(strict_types=1)` everywhere; explicit parameter and return types; `final` by default.
- No dead code, no commented-out code, no `TODO` without a linked plan item, no debug helpers.
- Comments only for non-obvious intent; PHPDoc with array shapes where types cannot express them.
- No speculative abstraction: an interface exists only when there are two implementations or a test seam needs it (`Clock` qualifies).
- Descriptive names (`isFavorite`, `remainingSeconds`), TitleCase enum cases.
- Pint clean, Larastan level 8 with zero baseline entries, every public action and builder method covered by a test.
- Every Livewire component has a `Livewire::test()` feature test for each public action, plus one authorization-failure test.
- One concept per commit; the commit message states the parity-matrix row or plan item it closes.

---

## 5. Domain

> The schema below is the **target**. Reconcile column names and types with `wellto.io/database/migrations` in Phase 0 and keep wellto's names where they exist, so a data migration script is trivial.

### 5.1 Data model

`users`: `id`, `name`, `email` (unique), `password`, **`palette`** (string, default `ferrite`, cast to `Palette`), timestamps.

`entries`: `id`, `user_id` (FK, cascade delete, indexed), `issuer`, `account_label`, `secret` (**encrypted cast**, text), `color` (nullable, cast to `TagColor`), `is_favorite` (bool, default false), timestamps. Index `(user_id, is_favorite, issuer)`.

If wellto stores digits, period or algorithm per entry, keep those columns. If it hard-codes six digits, 30 seconds and SHA-1 (as the README implies), keep them as constants in `Totp` and do not add columns.

### 5.2 Enums

```php
enum TagColor: string
{
    case Red = 'red';   // final case list comes from wellto's palette of tags (verify)
    // …
}

enum Palette: string
{
    case Ferrite = 'ferrite';
    case FerriteLight = 'ferrite-light';
    // … 15 cases, see section 7

    public function isDark(): bool { /* match over cases */ }
    public function label(): string { /* … */ }
    public static function default(): self { return self::Ferrite; }
}
```

### 5.3 Custom builder

```php
/** @extends Builder<Entry> */
final class EntryBuilder extends Builder
{
    public function ownedBy(User $user): self
    {
        return $this->where('user_id', $user->getKey());
    }

    public function favoritesFirst(): self
    {
        return $this->orderByDesc('is_favorite')->orderBy('issuer')->orderBy('account_label');
    }

    public function matching(string $term): self
    {
        $term = trim($term);

        return $term === ''
            ? $this
            : $this->where(fn (self $q) => $q
                ->where('issuer', 'like', "%{$this->escapeLike($term)}%")
                ->orWhere('account_label', 'like', "%{$this->escapeLike($term)}%"));
    }

    public function coloured(TagColor $color): self
    {
        return $this->where('color', $color);
    }
}
```

Registered on the model with `#[UseEloquentBuilder(EntryBuilder::class)]` **(verify)**. Rules:

- Search runs on `issuer` and `account_label` **only**. The secret is encrypted and must never be searched, selected into a list view unnecessarily, or logged.
- `escapeLike()` escapes `%`, `_` and `\` so user input cannot widen a search.
- Every method returns `self` for chaining and is covered by a unit test on the three drivers CI supports (SQLite required, MySQL optional).

### 5.4 TOTP (RFC 6238)

```
Base32::decode(string): string            // strips spaces and '=', case-insensitive, rejects invalid chars
Totp::code(string $secret, int $at): TotpCode
TotpCode { string $value; int $remainingSeconds; int $period }
```

- HMAC-SHA1 over the 8-byte big-endian counter `floor($at / 30)`, dynamic truncation, modulo 10^6, left-padded to six digits.
- The clock is injected (`Clock` interface returning a Unix timestamp) so tests do not sleep.
- Validation: Base32 alphabet, **at least 16 characters** after normalisation (wellto rule), decodes to non-empty bytes.
- **Golden vectors:** the RFC 6238 appendix secret `12345678901234567890` at T = 59 gives `94287082` (8-digit), so the six-digit code is `287082`. Add the other appendix times for SHA-1 as well. Use these as the first test written.

### 5.5 Action catalogue

| Action | Signature (typed) | Behaviour |
|---|---|---|
| `RegisterUser` | `handle(string $name, string $email, string $password): User` | Create user, default palette, fire `Registered`, return user |
| `AddEntry` | `handle(User $user, string $issuer, string $accountLabel, string $secret, ?TagColor $color): Entry` | Normalise and validate secret through `Base32`, create entry |
| `RemoveEntry` | `handle(Entry $entry): void` | Delete |
| `SetEntryFavorite` | `handle(Entry $entry, bool $favorite): Entry` | Idempotent set, not toggle, so double-clicks and retries are safe |
| `SetEntryColor` | `handle(Entry $entry, ?TagColor $color): Entry` | Set or clear tag |
| `GenerateEntryCodes` | `handle(Collection $entries): Collection<int, TotpCode>` | Decrypt and compute codes keyed by entry id; the only place secrets are read for display |
| `ChangePalette` | `handle(User $user, Palette $palette): User` | Persist palette |
| `SignOutUser` | `handle(): void` | Logout, invalidate session, regenerate token |

Sign-in uses Laravel's `Auth::attempt` behind a rate limiter in the component; it needs no separate action unless wellto's flow does more **(verify)**.

### 5.6 Policy

`EntryPolicy`: `view`, `update`, `delete` all return `$user->is($entry->user)`. No admin override.

---

## 6. UI specification (Livewire 4)

### 6.1 Routes

```php
Route::livewire('/login', 'pages::auth.login')->middleware('guest')->name('login');
Route::livewire('/register', 'pages::auth.register')->middleware('guest')->name('register');

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::vault.index')->name('vault');
    Route::livewire('/settings/appearance', 'pages::settings.appearance')->name('settings.appearance');
    Route::post('/logout', LogoutController::class)->name('logout');   // or a Livewire action, see 6.5
});
```

If wellto's URL structure differs, keep wellto's URLs for parity and add redirects only if routes are renamed **(verify in Phase 0)**.

### 6.2 `config/livewire.php`

```php
'component_layout'      => 'layouts::app',
'component_placeholder' => 'livewire.placeholder',
'make_command'          => ['type' => 'sfc'],
```

Namespaces `pages::` and `layouts::` are provided by default. Do not invent a third namespace unless the 4.x config key is confirmed **(verify)**.

### 6.3 Component inventory

| Component | Kind | Responsibility |
|---|---|---|
| `layouts::app`, `layouts::guest` | Blade layouts | `<html>` attributes, head partial, Flux scripts and styles, `.cozy-bg` |
| `pages::auth.login` | SFC page, `guest` layout | Email, password, remember, rate-limited attempt |
| `pages::auth.register` | SFC page, `guest` layout | Calls `RegisterUser`, signs in |
| `pages::vault.index` | SFC page | Composes toolbar, list, add-entry modal |
| `vault.entry-list` | SFC | Search (`#[Url]`), colour filter, favorites, remove; codes island |
| `vault.add-entry` | SFC (Flux modal) | Form object, calls `AddEntry` |
| `pages::settings.appearance` | SFC page | Hosts palette picker |
| `settings.palette-picker` | SFC | Lists `Palette::cases()`, calls `ChangePalette`, updates `<html>` |
| `ui.*` | Anonymous Blade components | Thin wrappers over Flux where Vaulta needs a fixed API (`ui.countdown`, `ui.tag-dot`) |

Entry cards are **Blade partials inside `entry-list`**, not child Livewire components. N nested components per page is an avoidable cost; `wire:key` plus islands gives per-region updates.

### 6.4 SFC conventions

- One concern per file; the PHP block, template, then `<style>` (if any), then `<script>` (if any).
- Full-page SFCs declare their layout with `new #[Layout('layouts::app')] class extends Component`; defaults come from config.
- Computed data uses `#[Computed]`; nothing derived is stored in public properties.
- Identifiers entering from the client are `#[Locked]`. Public properties never hold secrets, models with secrets, or generated codes.
- Search input: `wire:model.live.debounce.250ms="search"` with `#[Url(as: 'q', history: false)]`.
- Every list item has a stable `wire:key`.
- Component CSS stays scoped (default); anything that must style `<html>` or a theme lives in `resources/css/themes/`, never in a component.
- Component `<script>` blocks are used only for behaviour tied to that component (clipboard, countdown). Shared behaviour goes in `resources/js/`.

### 6.5 Reference SFC: entry list

```blade
<?php

use App\Actions\Entries\RemoveEntry;
use App\Actions\Entries\SetEntryFavorite;
use App\Actions\Totp\GenerateEntryCodes;
use App\Models\Entry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Transition;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    #[Url(as: 'q', history: false)]
    public string $search = '';

    #[Computed]
    public function entries()
    {
        return Entry::query()
            ->ownedBy(auth()->user())
            ->matching($this->search)
            ->favoritesFirst()
            ->get();
    }

    public function setFavorite(int $entryId, bool $favorite, SetEntryFavorite $action): void
    {
        $entry = $this->ownedEntry($entryId);
        $this->authorize('update', $entry);
        $action->handle($entry, $favorite);
        unset($this->entries);
    }

    public function remove(int $entryId, RemoveEntry $action): void
    {
        $entry = $this->ownedEntry($entryId);
        $this->authorize('delete', $entry);
        $action->handle($entry);
        unset($this->entries);
    }

    #[Transition(skip: true)]
    public function refreshCodes(): void
    {
        unset($this->entries);
    }

    private function ownedEntry(int $entryId): Entry
    {
        return Entry::query()->ownedBy(auth()->user())->findOrFail($entryId);
    }
};
?>

<div>
    <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" />

    @island(name: 'codes')
        @php($codes = app(GenerateEntryCodes::class)->handle($this->entries))
        <ul wire:transition="entry">
            @foreach ($this->entries as $entry)
                <li wire:key="entry-{{ $entry->id }}" wire:transition="entry-card">…</li>
            @endforeach
        </ul>
    @endisland
</div>
```

The sketch is illustrative: final markup, the `@island` options, and where codes are computed (view versus a `#[Computed]` that returns only codes) are settled in Phase 4 **(verify island semantics)**. The invariants are fixed: ownership resolution through the builder, authorization before the action, one action per intent, secrets never in public state.

### 6.6 Live codes and the countdown

Server time is authoritative because codes are computed on the server.

1. The island renders each code and a `data-server-time` (Unix seconds) and `data-period="30"` once per island.
2. Alpine computes `offset = serverTime - Date.now()/1000` at mount; the countdown ring or bar uses `offset` so a skewed client clock never shows a wrong "seconds left".
3. At each period boundary the client triggers a re-render of **only the codes island**. The trigger mechanism is **(verify)** against 4.x docs: preferred is a programmatic island refresh; fallback is a hidden `wire:click="refreshCodes" wire:island="codes"` button clicked from Alpine. `wire:poll` is rejected because it cannot align to 30-second boundaries.
4. On `visibilitychange` to visible, refresh immediately (a backgrounded tab misses boundaries).
5. `refreshCodes` carries `#[Transition(skip: true)]` so thirty-second refreshes do not cross-fade every card.
6. The countdown is Alpine-only and never inside a `wire:transition` element.

### 6.7 Copy to clipboard

Alpine handler reads the code already in the DOM and calls `navigator.clipboard.writeText()`; no server round-trip. Requires a secure context (HTTPS or localhost); on failure show an inline "Copy failed" state using a textarea-selection fallback. Visual confirmation uses the same `ui.countdown`-adjacent pattern as other micro-feedback (no toast dependency).

### 6.8 Modal

Flux modal for add-entry. Ferrite's rule applies: dialogs must inherit page colour (`<dialog>` resets text colour). This is part of the theme contract (section 7.4), not a per-component fix.

### 6.9 Accessibility

- Countdown exposes `role="timer"` and does not announce every second; announce only on code change using `aria-live="polite"` on a visually-hidden status node.
- All icon buttons have labels; favorite is `aria-pressed`.
- Focus returns to the invoking control when the modal closes.
- Colour tags always pair colour with a text or shape cue.
- All motion respects `prefers-reduced-motion` (section 8).

---

## 7. Theming specification

### 7.1 What is ported, unchanged in intent

All 15 palettes in section 3.2: identity, tokens, fonts, overlays, progress-bar skin. Per-account choice persisted in `users.palette`; default `ferrite`; guests see the last palette their browser saw.

### 7.2 What changes, and why

| Ferrite today | Vaulta | Reason |
|---|---|---|
| Palette list in `User::PALETTES`, light list repeated in `User`, `head.blade.php` and the appearance page | One `Palette` backed enum with `isDark()`, `label()`, `fonts()`; the head partial and picker receive data from it (`@js(Palette::lightValues())`) | Their own docs list three copies of the same list; a new theme should be one enum case, one CSS file |
| Single `app.css` holding every theme | `resources/css/themes/{id}.css`, one file per palette, plus `_contract.css`, imported by `app.css` | Reviewable diffs, no 15-theme merge conflicts |
| Light selectors `html:not(.dark)[data-palette]`, dark `.dark[data-palette]` | Both scoped to `[data-palette='id']` on **any element** (`.dark` only toggles dark variants) | Allows live preview cards in the picker instead of screenshot PNGs |
| Theme screenshots in `public/img/themes/*.png` | Live CSS preview cards: a small element with `data-palette` and (for dark palettes) `class="dark"` | No stale screenshots, always matches the real tokens |
| Fonts via Bunny Fonts | **Self-hosted** WOFF2 files, `@font-face` per theme (browsers fetch only fonts actually used) | A credential vault should not ping a third-party font CDN; simplifies CSP |
| `upload-bar` / `upload-fill` skin | Re-purposed as `.totp-bar` / `.totp-fill`, the 30-second countdown | Vaulta has no uploads; the countdown is the equivalent signature element |

### 7.3 Palette enum and persistence

- `users.palette` string, default `'ferrite'`, cast to `Palette`.
- Validation via `Rule::enum(Palette::class)` in the picker; unknown stored values fall back to `Palette::default()` through the cast's `tryFrom`.
- A migration normalises any invalid existing values to `ferrite` (mirrors Ferrite's `make_ferrite_the_default_palette` approach).

### 7.4 Token contract (`_contract.css`)

Every palette file must define, at minimum:

1. The zinc scale entries it overrides. **Dark palettes: `zinc-700/800/900/950`. Light palettes: whatever subset of the scale it needs, documented in its file header.**
2. `--color-accent`-family values plus the on-accent text colour.
3. `--radius-*` and `--shadow-*` (or an explicit reset to none).
4. Optional font variables: `--font-sans`, `--font-serif`, `--font-ui`, `--font-ui-adjust`.
5. `color-scheme` matching its mode.
6. A `.cozy-bg` rule (may be empty for a plain background).
7. A `.totp-bar` / `.totp-fill` skin.

And `_contract.css` itself defines, once: dialog colour inheritance, `.cozy-bg` base, `.totp-bar` base, overlay layer defaults (fixed, `pointer-events: none`, `z-index` ceiling), and the `prefers-reduced-motion` rules.

### 7.5 Application flow

1. **Authed request:** the layout renders `<html data-palette="{{ $palette->value }}" class="{{ $palette->isDark() ? 'dark' : '' }}">`.
2. **Guest request:** an inline head script runs before first paint, reads `localStorage['vaulta.palette']`, validates it against the server-injected allow-list and light-list, and sets `data-palette` and the `dark` class. It must work with the CSP nonce (section 9).
3. **Change in the picker:** the Livewire action persists via `ChangePalette`; the client applies `data-palette`, toggles `dark`, writes `localStorage`, optionally inside `document.startViewTransition()` when supported and motion is allowed.
4. **Sign-out:** the last palette stays in `localStorage` so the sign-in page keeps the look.

### 7.6 Fonts

- Files in `resources/fonts/{family}/…woff2`, loaded with `@font-face` and `font-display: swap`.
- Families required: JetBrains Mono, Public Sans, Pixelify Sans, Tinos, Courier Prime, Quicksand, Fredoka, Poppins, DotGothic16, Jersey 10, IBM Plex Mono, VT323, Press Start 2P, Outfit, Righteous, Share Tech Mono, Silkscreen, Libre Franklin. Confirm each family's licence (these are predominantly SIL OFL) and store the licence text with the files.
- `--font-ui-adjust` is applied through `font-size-adjust` to headings and `--font-ui` elements only when set.

### 7.7 Adding a theme (the whole procedure)

1. Add a case to `Palette` (id, label, `isDark()` entry).
2. Add `resources/css/themes/{id}.css` satisfying the contract.
3. Add any new font files and `@font-face`.
4. Run the theme tests (section 7.8). The picker card appears automatically.

### 7.8 Theme tests

- Every `Palette` case has a CSS file and a `[data-palette='{id}']` selector in it.
- Every CSS `data-palette` selector corresponds to an enum case (no orphan themes).
- `isDark()` agrees with the `color-scheme` declared in the theme file.
- Picker renders exactly `count(Palette::cases())` cards, each with a label.
- `ChangePalette` persists and rejects unknown values.
- A script (`composer contrast`) computes WCAG contrast for primary text on surfaces, accent text on accent, and muted text on surfaces per palette, and fails below 4.5:1 for body text and 3:1 for large text and UI borders. Palettes that cannot meet this by design (a deliberately low-contrast period look) carry an explicit, reviewed exception list in the script.

---

## 8. Motion specification

### 8.1 Principles

- Transitions clarify change (a card arrived, a card left, a view moved); they never decorate.
- All defined in CSS against `::view-transition-*`, never in JS timers.
- Every animation sits inside `@media (prefers-reduced-motion: no-preference)`.
- Unsupported browsers (older Firefox, pre-18 Safari) get instant updates with identical function.
- No more than one named transition animating per interaction.

### 8.2 Transition map

| Interaction | Mechanism | Detail |
|---|---|---|
| Entry added / removed / reordered by favorite | `wire:transition="entry-card"` on each `<li>` | Old/new pseudo-elements: short fade and slight rise; group animation moves siblings |
| Search filters the list | `wire:transition="entry"` on the `<ul>` container | Fade only, 120 ms; no per-card motion while typing |
| 30-second code refresh | `#[Transition(skip: true)]` on `refreshCodes` | No transition; the countdown ring resetting is the signal |
| Add-entry modal open/close | `wire:transition="modal"` on the modal surface | Scale from 0.98 plus fade; backdrop fades |
| Settings navigation (appearance ↔ profile, if present) | `#[Transition(type: 'forward' | 'backward')]` on navigation actions, `html:active-view-transition-type()` CSS | Directional slide; Firefox lacks types, so it falls back to a plain cross-fade |
| Palette change | `document.startViewTransition()` around the attribute swap | Root cross-fade 180 ms; skipped under reduced motion |

### 8.3 Known pitfalls to design around

- Elements with `wire:transition` and no name receive `match-element`; cards that re-render every second would animate every second. This is why the codes island refresh skips transitions and the countdown lives outside.
- Fixed overlays (`html::before` for nokia and miami, `html::after` for commodore) take part in the root snapshot; give them a stable `view-transition-name` or verify they do not flicker during the palette cross-fade.
- Two elements sharing one `view-transition-name` in the same frame abort the transition; entry cards use `match-element` (unique per element), never a fixed name.
- Transition animations must not delay copy feedback or modal focus management.

---

## 9. Security specification

Honest model: secrets are encrypted at rest with `APP_KEY` through Laravel's encrypted cast. The server decrypts them to generate codes. This protects a leaked database dump or backup; it does **not** protect against someone holding both the server and `APP_KEY`, and it is not end-to-end encryption. `docs/security.md` states this plainly.

| Area | Requirement |
|---|---|
| Secret handling | Decrypted only in `GenerateEntryCodes`; never in public Livewire state, logs, exception context, or cache; `Entry` hides `secret` from serialisation (`$hidden`) |
| Livewire state | Public properties are client-visible and tamperable: IDs are `#[Locked]`, authorization on every action, no model with secrets in public props |
| AuthN | Argon2id/bcrypt via Laravel; session regeneration on login; invalidate and regenerate token on logout |
| Rate limiting | Login: per-email+IP limiter (Laravel's `RateLimiter`), with a clear lockout message; registration limited per IP; entry creation limited per user |
| Registration | Open/closed switch via `config('vaulta.registration')` (wellto leaves restriction to the deployer; default open for parity, documented) |
| Headers | CSP with nonce (Vite and the head script use `Vite::useCspNonce()`), `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `X-Frame-Options: DENY`, HSTS in production |
| Sessions/cookies | `Secure`, `HttpOnly`, `SameSite=Lax` in production |
| Key management | Document `APP_KEY` backup; document rotation using `APP_PREVIOUS_KEYS`; add a `vaulta:check` command that verifies every entry still decrypts |
| Clipboard | No auto-copy, no server logging of copies |
| Dependencies | `composer audit` and `npm audit` in CI |

---

## 10. Testing and quality gates

| Layer | What | Tooling |
|---|---|---|
| Unit | `Base32`, `Totp` golden vectors and edge cases (padding, case, spaces, invalid chars, 15-vs-16 length), `EntryBuilder` methods, `Palette` enum | Pest |
| Actions | Each action with real DB (SQLite in-memory), including idempotency of `SetEntryFavorite` | Pest |
| Components | `Livewire::test()` per public action: success, validation failure, authorization failure, cross-user id returns 404 | Pest + Livewire |
| Policies | Owner and non-owner | Pest |
| Architecture | Layering rules from 4.2 | Pest `arch()` |
| Themes | Section 7.8 | Pest + script |
| Browser (smoke) | Sign up, add entry, see code, copy, search, favorite, remove, change palette, sign out; screenshot sweep of vault page and modal across all 15 palettes | Pest browser tests (Playwright) **(optional, decide in Phase 1)** |
| Static | Pint, Larastan level 8, `composer audit` | CI |
| CI | Matrix PHP 8.3 and 8.5; SQLite required, MySQL optional job | GitHub Actions |

**Definition of done for any change:** Pint clean, Larastan clean, relevant tests written first or alongside, parity-matrix row updated.

---

## 11. Parity matrix

Status: all rows start `Pending`. "Source" is where the behaviour is defined; "Verify" is the Phase 0 check against wellto's code.

| # | wellto.io function | Vaulta artefact | Proving test | Verify |
|---|---|---|---|---|
| P1 | Registration | `pages::auth.register`, `RegisterUser` | register creates user, signs in, rejects dup email, validation messages | field set, password rules, post-register redirect |
| P2 | Sign-in | `pages::auth.login` | valid, invalid, throttled, remember | remember-me presence, redirect target |
| P3 | Sign-out | `SignOutUser` + control in app layout | session invalidated, redirected | method (POST/Livewire) and destination |
| P4 | Per-account vault isolation | `EntryBuilder::ownedBy`, `EntryPolicy` | user B cannot see/act on user A's entry via any component action | any shared/global routes |
| P5 | Encrypted secrets at rest | `Entry::$casts['secret' => 'encrypted']` | raw DB value ≠ plaintext; round-trips | cast type used (encrypted vs encrypted:string) |
| P6 | RFC 6238 six-digit codes | `Totp`, `GenerateEntryCodes` | RFC vectors | hash algorithm, digits, period |
| P7 | 30-second auto refresh | codes island + Alpine countdown | component refresh test; JS timing covered by browser smoke | refresh mechanism, countdown visual |
| P8 | Search | `EntryBuilder::matching`, `#[Url] $search` | filters by issuer and label; escaped wildcards | fields searched |
| P9 | Colour tags | `TagColor`, `SetEntryColor`, tag UI | set, clear, filter if wellto filters | exact tag list, UI to assign |
| P10 | Favorites | `SetEntryFavorite`, `favoritesFirst` | set/unset, ordering | ordering rule, UI placement |
| P11 | Copy to clipboard | Alpine handler | browser smoke | feedback behaviour |
| P12 | Account removal | `RemoveEntry`, confirmation modal | remove, other user 404 | confirmation present or not |
| P13 | Add entry from Base32 key, min 16 chars | `vault.add-entry`, `Base32`, `AddEntry` | accepts valid, rejects short/invalid | optional fields, label rules |
| P14 | Responsive dashboard | `pages::vault.index`, theme contract | browser smoke at three widths | breakpoints |
| P15 | Flux forms/buttons/modal | Flux components throughout | rendered in component tests | any non-Flux custom control |
| P16 | Env/setup parity (`composer setup`, SQLite default, `APP_KEY` docs) | `composer.json` scripts, README | CI runs `composer setup` | scripts in wellto's composer.json (setup/dev/test seen) |
| P17 | *(possible)* account 2FA from starter kit | decided in Phase 0 | — | whether `bacon/bacon-qr-code` is used |

Rows P17 and anything discovered in source are added to `docs/parity.md` before Phase 2 begins.

---

## 12. Open questions (answer before Phase 3)

1. **Licence** of the result (section 0.3).
2. Does wellto have profile/password/delete-account settings screens, or 2FA? (Phase 0 answers; matrix grows if yes.)
3. Exact colour-tag palette and where tags are assigned.
4. Does the vault list group by favorites, or just sort?
5. Is `Pest browser testing` acceptable as a dev dependency?
6. Self-hosted fonts approved over Bunny Fonts?
7. Should registration be open by default (parity) or closed after first user (Ferrite's Welcome-page model)?
