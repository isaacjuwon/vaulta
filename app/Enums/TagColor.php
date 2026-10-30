<?php

declare(strict_types=1);

namespace App\Enums;

enum TagColor: string
{
    case Zinc = 'zinc';
    case Red = 'red';
    case Amber = 'amber';
    case Emerald = 'emerald';
    case Sky = 'sky';
    case Violet = 'violet';
    case Pink = 'pink';

    public static function default(): self
    {
        return self::Zinc;
    }

    public function label(): string
    {
        return match ($this) {
            self::Zinc => 'Default',
            self::Red => 'Red',
            self::Amber => 'Amber',
            self::Emerald => 'Emerald',
            self::Sky => 'Sky',
            self::Violet => 'Violet',
            self::Pink => 'Pink',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Zinc => 'bg-zinc-400 dark:bg-zinc-500',
            self::Red => 'bg-red-500',
            self::Amber => 'bg-amber-500',
            self::Emerald => 'bg-emerald-500',
            self::Sky => 'bg-sky-500',
            self::Violet => 'bg-violet-500',
            self::Pink => 'bg-pink-500',
        };
    }

    public function ringClass(): string
    {
        return match ($this) {
            self::Zinc => 'border-zinc-300 text-zinc-700 dark:border-zinc-600 dark:text-zinc-300',
            self::Red => 'border-red-500/40 text-red-700 dark:text-red-300',
            self::Amber => 'border-amber-500/40 text-amber-800 dark:text-amber-300',
            self::Emerald => 'border-emerald-500/40 text-emerald-700 dark:text-emerald-300',
            self::Sky => 'border-sky-500/40 text-sky-700 dark:text-sky-300',
            self::Violet => 'border-violet-500/40 text-violet-700 dark:text-violet-300',
            self::Pink => 'border-pink-500/40 text-pink-700 dark:text-pink-300',
        };
    }

    public function shapeCue(): string
    {
        return match ($this) {
            self::Zinc => '○',
            self::Red => '◆',
            self::Amber => '▲',
            self::Emerald => '●',
            self::Sky => '■',
            self::Violet => '✦',
            self::Pink => '♥',
        };
    }

    /**
     * @return list<self>
     */
    public static function filterableCases(): array
    {
        return [
            self::Red,
            self::Amber,
            self::Emerald,
            self::Sky,
            self::Violet,
            self::Pink,
        ];
    }
}
