# Vaulta — Ferrite Multi-Theme Inventory

Extracted from `ferrite/resources/css/app.css`, `ferrite/vite.config.js`, `ferrite/resources/views/partials/head.blade.php`, and `ferrite/app/Models/User.php`.
License compatibility: Vaulta is licensed **AGPL-3.0-or-later** matching Ferrite.

## 1. Architecture Delta (`Ferrite` → `Vaulta`)

1. **Single Source of Truth Enum**: `App\Enums\Palette` (backed string enum, TitleCase cases) replaces `User::PALETTES`, `User::LIGHT_PALETTES`, and view-level arrays.
2. **Element-Scoped Selectors**: Instead of `html:not(.dark)[data-palette='x']` and `.dark[data-palette='x']` restricted to `<html>`, Vaulta scopes tokens to `[data-palette='x']` (and `.dark[data-palette='x']` for dark palettes) so **live CSS preview cards** in `settings.palette-picker` render authentic tokens, radii, shadows, fonts, and `.totp-bar` skins without PNG screenshots.
3. **Modular CSS Files**: `resources/css/themes/_contract.css` + 15 files `resources/css/themes/{id}.css` imported by `resources/css/app.css`.
4. **Self-Hosted WOFF2 Fonts**: Stored in `resources/fonts/{family}/` with SIL OFL / Apache-2.0 license files and registered via `local()` in `vite.config.js` (zero external network requests at build or runtime).
5. **`.totp-bar` / `.totp-fill` Skin**: Replaces Ferrite's `.upload-bar` / `.upload-fill` as the signature progress indicator on TOTP countdown bars.

## 2. Shared Contract (`_contract.css`)

- **Base `@theme` defaults**: JetBrains Mono (`--font-sans`, `--font-serif`), warm paper zinc 50–950, rust accent `#c8643a`.
- **Border defaults**: `border-color: var(--color-zinc-200, currentColor)` in light; `var(--color-zinc-700, currentColor)` inside `.dark`.
- **`color-scheme`**: `light` on `[data-palette]` light themes, `dark` on `.dark` / dark themes.
- **Modal inheritance**: `dialog { color: inherit; }`, `dialog[data-modal] { background-color: var(--color-zinc-50); }`, `.dark dialog[data-modal] { background-color: var(--color-zinc-800); }`.
- **Typography hooks**:
  - `h1, h2, h3, [data-flux-heading]` → `font-family: var(--font-serif); font-size-adjust: var(--font-ui-adjust, none); letter-spacing: -0.01em;`
  - `button, label, th, summary, [role='menuitem'], [role='tab'], [data-flux-badge], [data-flux-sidebar] a, [data-flux-navlist] a` → `font-family: var(--font-ui, inherit); font-size-adjust: var(--font-ui-adjust, none);`
- **Dark secondary text**: `.dark [data-flux-text], .dark [data-flux-subheading] { color: var(--color-zinc-300); }`
- **Overlays ceiling**: `position: fixed; z-index: 2147483000; pointer-events: none;` with stable `view-transition-name` (`vaulta-overlay-before`, `vaulta-overlay-after`) so View Transitions never flicker.
- **Reduced motion gate**: All `::view-transition-*` animations wrapped in `@media (prefers-reduced-motion: no-preference)`.

## 3. Complete 15-Palette Token Matrix

### Dark Palettes (8)

| Palette (`Palette` Case) | Surface Zinc (`700 / 800 / 900 / 950`) | Text Zinc (`100 / 200 / 300 / 400 / 500`) | Accent / Content / Foreground | Fonts (`sans` / `serif` / `ui` / `adjust`) | Special (`--color-white`, Overlays, `.cozy-bg`, `.totp-bar`) |
|---|---|---|---|---|---|
| `ferrite` (`Ferrite`) | `#4a4540` / `#2a2724` / `#211f1c` / `#161412` | *(theme)* / `#f6ecd8` / `#ecdfc4` / `#dac8a6` / `#c2ab88` | `#e48a5e` / `#f0a37c` / `#2b1a10` | JetBrains Mono / JetBrains Mono / inherit / none | Plain dark `.cozy-bg`, default rounded accent `.totp-bar` |
| `amber` (`Amber`) | `#4a3408` / `#1c1204` / `#120b02` / `#080500` | `#ffc861` / `#ffbd45` / `#e9a834` / `#c28a26` / `#a07020` | `#ffb000` / `#ffc233` / `#120b02` | IBM Plex Mono / VT323 / VT323 / `0.52` | `--crt-glow: 255 176 0`, `--color-white: #ffc861`, radius `0`, scanlines + glow + SVG drop-shadow, segmented CRT `.totp-bar` |
| `phosphor` (`Phosphor`) | `#0f4a22` / `#04170a` / `#020f06` / `#010802` | `#8dffa8` / `#66ff8a` / `#3fe468` / `#2fb24f` / `#258c3e` | `#33ff66` / `#66ff8a` / `#021408` | IBM Plex Mono / VT323 / VT323 / `0.52` | `--crt-glow: 51 255 102`, `--color-white: #8dffa8`, shares CRT scanlines/glow/segmented `.totp-bar` |
| `commodore` (`Commodore`) | `#6c5eb5` / `#352879` / `#2b2063` / `#1f1750` | `#d6d0ff` / `#c4bdff` / `#a59ef9` / `#8a82e0` / `#7a72d0` | `#bfce72` / `#bfce72` / `#2b2063` | DotGothic16 / Press Start 2P / Press Start 2P / `0.4` | `--color-white: #d6d0ff`, radius `0`, shadows `none`, `html::after` 10px `#6c5eb5` C64 frame, segmented `.totp-bar` |
| `amiga` (`Amiga`) | `#8fb4e0` / `#0055aa` / `#004488` / `#002f66` | `#ffffff` / `#f0f6ff` / `#d4e4f7` / `#b5cfee` / `#9dbde6` | `#ff8800` / `#ffa033` / `#000000` | Share Tech Mono / Silkscreen / Silkscreen / none | Radius `0`, hard `#000` offset shadows (1–5px), 2px `#fff` border black `.totp-bar` with `#ff8800` fill |
| `miami` (`Miami`) | `#3b2a6b` / `#15122f` / `#0e0b26` / `#08061a` | `#fff3ea` / `#ffe7d9` / `#f7d6cb` / `#dcbccb` / `#6fe3d3` | `#ff5fa2` / `#ff8fc0` / `#1a0b1a` | Outfit / Righteous / Righteous / none | Radii 0–12px, pink neon glow shadows, `html::before` 4px `#ff5fa2→#3de8d4` top rule, neon heading text-shadow, pill gradient `.totp-bar` |
| `lotus` (`Lotus`) | `#555555` / `#1a1a1a` / `#000000` / `#000000` | `#ffffff` / `#e4e4e4` / `#c8c8c8` / `#aaaaaa` / `#8a8a8a` | `#55ffff` / `#55ffff` / `#000000` | IBM Plex Mono / IBM Plex Mono / inherit / none | Radius `0`, shadows `none`, `32px × 120px` spreadsheet grid `.cozy-bg`, inverse selection, segmented `.totp-bar` |
| `wallstreet` (`Wallstreet`) | `#34456b` / `#172342` / `#0f1a33` / `#080f21` | `#f6f1e4` / `#e9e3d3` / `#d2ccbb` / `#dac8a6` / `#c2ab88` | `#d4af37` / `#e6c75a` / `#17120a` | Libre Franklin / Libre Franklin / inherit / none | Small radii (1–8px), subtle pinstripes + gold radial `.cozy-bg`, gold gradient `.totp-bar` |

### Light Palettes (7)

| Palette (`Palette` Case) | Zinc Scale (`50` → `950`) | Accent / Content / Foreground | Fonts (`sans` / `serif` / `ui` / `adjust`) | Special (Overlays, `.cozy-bg`, `.totp-bar`) |
|---|---|---|---|---|
| `ferrite-light` (`FerriteLight`) | `#faf8f5`, `#f2efea`, `#e4e0d9`, `#cfcac1`, `#a39d92`, `#7d776c`, `#5f5a51`, `#46423b`, `#2b2825`, `#211f1c`, `#161412` | `#b0502a` / `#9a4422` / `#fffaf0` | JetBrains Mono / JetBrains Mono / inherit / none | Warm paper light mode, default rounded `.totp-bar` |
| `mac` (`Mac`) | `#ffffff`, `#f1f1f1`, `#d9d9d9`, `#b8b8b8`, `#7a7a7a`, `#555555`, `#333333`, `#1a1a1a`, `#000000` (800–950) | `#000000` / `#000000` / `#ffffff` | Public Sans / Pixelify Sans / Pixelify Sans / none | Radius `0`, 1px `#000` borders, hard `#000` shadows, 4px dotted desktop `.cozy-bg`, diagonal-stripe `.totp-bar` |
| `desk95` (`Desk95`) | `#c0c0c0`, `#dfdfdf`, `#808080`, `#808080`, `#5a5a5a`, `#404040`, `#202020`, `#101010`, `#000000` (800–950) | `#000080` / `#000080` / `#ffffff` | Public Sans / Public Sans / inherit / none | Radius `0`, Win95 raised/sunken inset bevels on buttons/dialogs/inputs, sunken navy-block `.totp-bar` |
| `zine` (`Zine`) | `#ffffff`, `#f2f2f2`, `#cccccc`, `#aaaaaa`, `#777777`, `#555555`, `#333333`, `#1a1a1a`, `#000000` (800–950) | `#0000ee` / `#0000ee` / `#ffffff` | Tinos / Tinos / Courier Prime / none | Radius `0`, shadows `none`, underlined links, 1px `#000` solid `.totp-bar` |
| `bubblegum` (`Bubblegum`) | `#fff5fa`, `#ffe9f4`, `#f6d3e6`, `#e3b8d6`, `#b58aae`, `#8d628a`, `#6e4a72`, `#55345e`, `#3f2549`, `#321c3b`, `#25142c` | `#d6338a` / `#b81f72` / `#ffffff` | Quicksand / Fredoka / Fredoka / none | Pill radii (`0.5rem`–`2.5rem`), pink-tinted shadows, pastel radial `.cozy-bg`, white-bordered gradient pill `.totp-bar` |
| `memphis` (`Memphis`) | `#fffdf4`, `#fff1b8`, `#e4dcf5`, `#14141f` (300!), `#6b6a85`, `#55546e`, `#3f3e57`, `#2b2a40`, `#1d1c30`, `#14141f`, `#0d0d16` | `#ff6fae` / `#d1246f` / `#14141f` | Poppins / Poppins / inherit / none | Hard `#14141f` shadows (1–6px), 260×260 pastel confetti SVG `.cozy-bg`, 82% cream veil on `[data-flux-main]`, diagonal pink/mint `.totp-bar` |
| `nokia` (`Nokia`) | `#c8d6a4`, `#bccb98`, `#aebd8b`, `#92a272`, `#5f7047`, `#465835`, `#3a4a2c`, `#34422a`, `#28351f`, `#1f2a18`, `#151d10` | `#28351f` / `#28351f` / `#c8d6a4` | DotGothic16 / Jersey 10 / Jersey 10 / `0.55` | `--color-white: #d9e5b8`, radius `0`, 3px LCD pixel grid `.cozy-bg`, `html::before` 12px signal/battery status bar (`z-index: 2147483000`), segmented LCD `.totp-bar` |

## 4. Required Self-Hosted Font Families (18)

| Family | Weights | Used By |
|---|---|---|
| JetBrains Mono | 400, 500, 600, 700 | `ferrite`, `ferrite-light` (preloaded default) |
| Public Sans | 400, 500, 600, 700 | `mac`, `desk95` |
| Pixelify Sans | 500, 600, 700 | `mac` |
| Tinos | 400, 700 | `zine` |
| Courier Prime | 400, 700 | `zine` |
| Quicksand | 400, 500, 600, 700 | `bubblegum` |
| Fredoka | 500, 600, 700 | `bubblegum` |
| Poppins | 400, 500, 600, 700 | `memphis` |
| DotGothic16 | 400 | `nokia`, `commodore` |
| Jersey 10 | 400 | `nokia` |
| IBM Plex Mono | 400, 500, 700 | `amber`, `phosphor`, `lotus` |
| VT323 | 400 | `amber`, `phosphor` |
| Press Start 2P | 400 | `commodore` |
| Share Tech Mono | 400 | `amiga` |
| Silkscreen | 400, 700 | `amiga` |
| Outfit | 400, 500, 600, 700 | `miami` |
| Righteous | 400 | `miami` |
| Libre Franklin | 400, 500, 600, 700 | `wallstreet` |
