<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Enums\Palette;

$themesDir = dirname(__DIR__).'/resources/css/themes';

/**
 * Explicit reviewed exceptions for period-authentic aesthetics (SPEC §7.8).
 *
 * @var array<string, float>
 */
$mutedTextFloorOverrides = [
    // Commodore 64 authentic VIC-II light-blue/indigo muted secondary contrast (~4.1:1)
    'commodore' => 3.8,
    // Nokia 3310 backlit olive LCD secondary pixel tone (~3.9:1)
    'nokia' => 3.5,
];

/**
 * @var array<string, float>
 */
$accentFloorOverrides = [
    // Ferrite Bubblegum 98 canonical hot-pink #d6338a with white text is 4.47:1
    'bubblegum' => 4.45,
];

function relativeLuminance(string $hex): float
{
    $clean = ltrim(string: trim(string: $hex), characters: '#');
    if (strlen(string: $clean) === 3) {
        $clean = $clean[0].$clean[0].$clean[1].$clean[1].$clean[2].$clean[2];
    }

    $channels = [
        hexdec(hex_string: substr(string: $clean, offset: 0, length: 2)) / 255,
        hexdec(hex_string: substr(string: $clean, offset: 2, length: 2)) / 255,
        hexdec(hex_string: substr(string: $clean, offset: 4, length: 2)) / 255,
    ];

    $linear = array_map(
        callback: static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
        array: $channels,
    );

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

function contrastRatio(string $hexA, string $hexB): float
{
    $lumA = relativeLuminance(hex: $hexA);
    $lumB = relativeLuminance(hex: $hexB);

    $lighter = max($lumA, $lumB);
    $darker = min($lumA, $lumB);

    return ($lighter + 0.05) / ($darker + 0.05);
}

/**
 * @return array<string, string>
 */
function parseHexTokens(string $css): array
{
    $matches = [];
    preg_match_all(pattern: '/(--color-[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,6})\b/', subject: $css, matches: $matches, flags: PREG_SET_ORDER);

    $tokens = [];
    foreach ($matches as $match) {
        $tokens[$match[1]] = strtolower(string: $match[2]);
    }

    return $tokens;
}

$defaultTokens = [
    '--color-zinc-50' => '#fbf6ec',
    '--color-zinc-100' => '#f4ebdb',
    '--color-zinc-200' => '#e8dcc4',
    '--color-zinc-300' => '#d6c5a6',
    '--color-zinc-400' => '#b09a7c',
    '--color-zinc-500' => '#8a7459',
    '--color-zinc-600' => '#6b5845',
    '--color-zinc-700' => '#66533f',
    '--color-zinc-800' => '#4a3a2e',
    '--color-zinc-900' => '#3b2e24',
    '--color-zinc-950' => '#1c1511',
];

$errors = [];

foreach (Palette::cases() as $palette) {
    $file = $themesDir.'/'.$palette->value.'.css';
    if (! is_file(filename: $file)) {
        $errors[] = "[{$palette->value}] Missing stylesheet: {$file}";

        continue;
    }

    $css = (string) file_get_contents(filename: $file);
    $tokens = array_merge($defaultTokens, parseHexTokens(css: $css));

    $surface = $palette->isDark() ? $tokens['--color-zinc-800'] : $tokens['--color-zinc-50'];
    $primaryText = $palette->isDark() ? $tokens['--color-zinc-100'] : $tokens['--color-zinc-800'];
    $mutedText = $palette->isDark() ? $tokens['--color-zinc-300'] : $tokens['--color-zinc-600'];
    $accent = $tokens['--color-accent'] ?? '#000000';
    $accentForeground = $tokens['--color-accent-foreground'] ?? '#ffffff';

    $bodyContrast = contrastRatio(hexA: $primaryText, hexB: $surface);
    if ($bodyContrast < 4.5) {
        $errors[] = sprintf(
            '[%s] Primary text (%s) on surface (%s) is %.2f:1 (minimum 4.5:1)',
            $palette->value,
            $primaryText,
            $surface,
            $bodyContrast,
        );
    }

    $accentFloor = $accentFloorOverrides[$palette->value] ?? 4.5;
    $accentContrast = contrastRatio(hexA: $accentForeground, hexB: $accent);
    if ($accentContrast < $accentFloor) {
        $errors[] = sprintf(
            '[%s] Accent foreground (%s) on accent (%s) is %.2f:1 (minimum %.2f:1)',
            $palette->value,
            $accentForeground,
            $accent,
            $accentContrast,
            $accentFloor,
        );
    }

    $mutedFloor = $mutedTextFloorOverrides[$palette->value] ?? 4.5;
    $mutedContrast = contrastRatio(hexA: $mutedText, hexB: $surface);
    if ($mutedContrast < $mutedFloor) {
        $errors[] = sprintf(
            '[%s] Muted text (%s) on surface (%s) is %.2f:1 (minimum %.1f:1)',
            $palette->value,
            $mutedText,
            $surface,
            $mutedContrast,
            $mutedFloor,
        );
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Theme contrast audit failed:\n - ".implode(separator: "\n - ", array: $errors)."\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Theme contrast audit passed across all %d palettes.\n", count(Palette::cases())));
exit(0);
