# Vaulta Theming System

Vaulta ports the complete 15-palette theming architecture from **Ferrite** into a zero-external-request Laravel 13 + Tailwind CSS v4 + Flux UI v2 design system.

## Token Contract (`resources/css/themes/_contract.css`)

Every palette overrides the semantic CSS custom properties consumed by Tailwind v4 and Flux UI on `:root[data-palette="<slug>"]`:

- **Neutral Ramp**: `--color-zinc-50` through `--color-zinc-950` (remapped from light-to-dark on light palettes and dark-to-light surface hierarchy as required by Flux).
- **Accent**: `--color-accent`, `--color-accent-content`, `--color-accent-foreground`.
- **Atmosphere**: `--cozy-bg` (layered radial/linear gradients, scanlines, grids, or paper textures) and `--cozy-fg`.
- **Typography**: `--font-sans` and `--font-mono`, resolved purely from self-hosted `.woff2` files in `resources/fonts/{slug}/` via `laravel-vite-plugin`'s `local()` font provider.

## Built-in Palettes (15)

| Slug | Label | Mode | Sans Font | Mono Font | Vibe |
| --- | --- | --- | --- | --- | --- |
| `ferrite` | Ferrite | Dark | Space Grotesk | JetBrains Mono | Warm iron & molten amber (default) |
| `ferrite-light` | Ferrite Light | Light | Space Grotesk | JetBrains Mono | Warm alabaster & forged copper |
| `mac` | Classic Mac | Light | Plus Jakarta Sans |Space Mono | Platinum bevels & Chicago crispness |
| `desk95` | Desk 95 | Light | Public Sans | IBM Plex Mono | Teal desktop & 3D raised chrome |
| `zine` | Zine | Light | Fraunces | Courier Prime | Photocopied paper & risograph ink |
| `bubblegum` | Bubblegum | Light | Quicksand | Fira Code | Pastel candy & glossy magenta |
| `memphis` | Memphis | Light | Syne | Space Mono | Postmodern geometry & bold primaries |
| `nokia` | Brick Phone | Light | Chakra Petch | VT323 | Olive LCD matrix & keypad slate |
| `amber` | Amber CRT | Dark | IBM Plex Mono | VT323 | Warm P3 phosphor terminal |
| `phosphor` | Green Phosphor | Dark | Share Tech Mono | Share Tech Mono | Classic P1 monochrome monitor |
| `commodore` | 64K | Dark | Chakra Petch | Press Start 2P | Royal indigo border & periwinkle BASIC |
| `miami` | Neon Outrun | Dark | Outfit | Fira Code | Midnight violet & sunset cyan-magenta |
| `amiga` | Workbench | Dark | Rajdhani | Share Tech Mono | Cobalt blue & high-visibility orange |
| `wallstreet` | Terminal | Dark | IBM Plex Sans | IBM Plex Mono | High-density market amber on obsidian |
| `lotus` | 1-2-3 | Dark | IBM Plex Mono | IBM Plex Mono | Cyan cell borders & spreadsheet midnight |

## Zero-FOUC & View Transitions

1. Authenticated pages render `<html data-palette="{{ $palette->value }}" @class(['dark' => $palette->isDark()])>` directly from `$user->resolvedPalette()`.
2. `resources/views/partials/head.blade.php` runs a CSP-nonced inline script before first paint that restores `localStorage['vaulta.palette']` for guests and syncs the authenticated palette back to `localStorage`.
3. Selecting a palette in **Settings → Appearance** (or on the guest landing page) wraps the DOM attribute swap in `document.startViewTransition(...)`, cross-fading in 180 ms while respecting `prefers-reduced-motion: reduce`.

## Adding a New Theme in 4 Steps

1. **Create CSS**: Add `resources/css/themes/<slug>.css` defining `:root[data-palette="<slug>"]` with `--color-zinc-50..950`, `--color-accent*`, `--cozy-bg`, `--cozy-fg`, `--font-sans`, and `--font-mono`, then `@import` it in `resources/css/app.css`.
2. **Bundle Fonts (if new)**: Place `.woff2` files and `LICENSE.txt` under `resources/fonts/<font-slug>/` and register the family in `vite.config.js` via `fonts({ local: ... })`.
3. **Register Enum Case**: Add a TitleCase case to `App\Enums\Palette` and wire its `label()`, `tagline()`, `isDark()`, `icon()`, and `previewColors()` match arms.
4. **Verify Contrast**: Run `composer contrast` (`php scripts/contrast.php`) to verify WCAG AA foreground/background and accent contrast ratios.
