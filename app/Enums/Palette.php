<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;

enum Palette: string
{
    case Ferrite = 'ferrite';
    case FerriteLight = 'ferrite-light';
    case Mac = 'mac';
    case Desk95 = 'desk95';
    case Zine = 'zine';
    case Bubblegum = 'bubblegum';
    case Memphis = 'memphis';
    case Nokia = 'nokia';
    case Amber = 'amber';
    case Phosphor = 'phosphor';
    case Commodore = 'commodore';
    case Amiga = 'amiga';
    case Miami = 'miami';
    case Wallstreet = 'wallstreet';
    case Lotus = 'lotus';

    public static function default(): self
    {
        try {
            $custom = SiteSetting::get(key: 'default_theme');

            if (is_string(value: $custom) && $custom !== '') {
                $resolved = self::tryFrom(value: $custom);
                if ($resolved instanceof self) {
                    return $resolved;
                }
            }
        } catch (\Throwable) {
            // Table not yet available
        }

        return self::Ferrite;
    }

    public static function current(?Request $request = null): self
    {
        /** @var ?User $user */
        $user = auth()->user();
        if ($user instanceof User && $user->palette !== null) {
            return $user->resolvedPalette();
        }

        $request ??= request();
        $cookie = $request->cookie(key: 'vaulta_palette');

        if (is_string(value: $cookie) && $cookie !== '') {
            return self::resolve(value: $cookie);
        }

        return self::default();
    }

    public static function resolve(?string $value): self
    {
        if ($value === null) {
            return self::default();
        }

        return self::tryFrom(value: $value) ?? self::default();
    }

    public function isDark(): bool
    {
        return match ($this) {
            self::FerriteLight,
            self::Mac,
            self::Desk95,
            self::Zine,
            self::Bubblegum,
            self::Memphis,
            self::Nokia => false,
            self::Ferrite,
            self::Amber,
            self::Phosphor,
            self::Commodore,
            self::Amiga,
            self::Miami,
            self::Wallstreet,
            self::Lotus => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ferrite => 'Ferrite',
            self::FerriteLight => 'Ferrite light',
            self::Mac => 'Classic Mac',
            self::Desk95 => 'Desktop 95',
            self::Zine => 'Plain page',
            self::Bubblegum => 'Bubblegum 98',
            self::Memphis => 'Memphis',
            self::Nokia => 'Nokia 3310',
            self::Amber => 'Amber terminal',
            self::Phosphor => 'Green phosphor',
            self::Commodore => 'Commodore',
            self::Amiga => 'Amiga',
            self::Miami => 'Miami Vice',
            self::Wallstreet => 'Wall Street',
            self::Lotus => 'Lotus 1-2-3',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::Ferrite => 'Warm graphite & tape oxide',
            self::FerriteLight => 'Warm archival paper & rust',
            self::Mac => 'System 7 1-bit crisp desktop',
            self::Desk95 => 'Beveled silver & navy',
            self::Zine => 'Editorial serif & courier',
            self::Bubblegum => 'Pastel lilac & hot pink',
            self::Memphis => 'Pastel confetti & hard ink',
            self::Nokia => 'Backlit monochrome LCD grid',
            self::Amber => 'P3 amber CRT scanlines',
            self::Phosphor => 'P1 green phosphor terminal',
            self::Commodore => 'C64 64K blue screen frame',
            self::Amiga => 'Workbench 1.3 blue & orange',
            self::Miami => 'Neon indigo & turquoise',
            self::Wallstreet => 'Navy pinstripes & brass',
            self::Lotus => 'DOS cyan worksheet grid',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ferrite => 'moon',
            self::FerriteLight => 'sun',
            self::Mac => 'computer-desktop',
            self::Desk95 => 'window',
            self::Zine => 'newspaper',
            self::Bubblegum => 'sparkles',
            self::Memphis => 'star',
            self::Nokia => 'device-phone-mobile',
            self::Amber => 'terminal',
            self::Phosphor => 'tv',
            self::Commodore => 'gamepad-2',
            self::Amiga => 'save',
            self::Miami => 'bolt',
            self::Wallstreet => 'briefcase',
            self::Lotus => 'table-cells',
        };
    }

    /**
     * @return array{bg: string, surface: string, fg: string, accent: string}
     */
    public function previewColors(): array
    {
        return match ($this) {
            self::Ferrite => ['bg' => '#141312', 'surface' => '#1e1c1a', 'fg' => '#ede8e0', 'accent' => '#e07a3a'],
            self::FerriteLight => ['bg' => '#f6f2eb', 'surface' => '#ede7dc', 'fg' => '#1f1c18', 'accent' => '#b34b18'],
            self::Mac => ['bg' => '#e6e6e6', 'surface' => '#ffffff', 'fg' => '#000000', 'accent' => '#000000'],
            self::Desk95 => ['bg' => '#008080', 'surface' => '#c0c0c0', 'fg' => '#000000', 'accent' => '#000080'],
            self::Zine => ['bg' => '#f5f0e4', 'surface' => '#fbf8f1', 'fg' => '#141210', 'accent' => '#c92a2a'],
            self::Bubblegum => ['bg' => '#fdf2f8', 'surface' => '#ffffff', 'fg' => '#3b0a2a', 'accent' => '#d6338a'],
            self::Memphis => ['bg' => '#fff9eb', 'surface' => '#ffffff', 'fg' => '#141414', 'accent' => '#1d4ed8'],
            self::Nokia => ['bg' => '#9bbc0f', 'surface' => '#8bac0f', 'fg' => '#0f380f', 'accent' => '#0f380f'],
            self::Amber => ['bg' => '#120c04', 'surface' => '#1c1306', 'fg' => '#ffb000', 'accent' => '#ffb000'],
            self::Phosphor => ['bg' => '#061208', 'surface' => '#0b1e0f', 'fg' => '#33ff66', 'accent' => '#33ff66'],
            self::Commodore => ['bg' => '#352879', 'surface' => '#40318d', 'fg' => '#a093e8', 'accent' => '#a093e8'],
            self::Amiga => ['bg' => '#0055aa', 'surface' => '#003d7a', 'fg' => '#ffffff', 'accent' => '#ff8800'],
            self::Miami => ['bg' => '#120824', 'surface' => '#1d0e38', 'fg' => '#f8eaff', 'accent' => '#00f0ff'],
            self::Wallstreet => ['bg' => '#0a0d12', 'surface' => '#121720', 'fg' => '#f3e5c8', 'accent' => '#f59e0b'],
            self::Lotus => ['bg' => '#001622', 'surface' => '#002233', 'fg' => '#e0f7ff', 'accent' => '#00d4ff'],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            callback: static fn (self $palette): string => $palette->value,
            array: self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function lightValues(): array
    {
        return array_values(
            array: array_map(
                callback: static fn (self $palette): string => $palette->value,
                array: array_filter(
                    array: self::cases(),
                    callback: static fn (self $palette): bool => ! $palette->isDark(),
                ),
            ),
        );
    }
}
