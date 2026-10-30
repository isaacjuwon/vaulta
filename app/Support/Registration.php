<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\User;

final readonly class Registration
{
    public static function mode(): string
    {
        $dbSetting = SiteSetting::get(key: 'registration_mode');
        $setting = $dbSetting ?? config(key: 'vaulta.registration', default: 'open');

        if (is_bool(value: $setting)) {
            return $setting ? 'open' : 'closed';
        }

        return is_scalar(value: $setting)
            ? mb_strtolower(string: trim(string: (string) $setting))
            : 'open';
    }

    public static function open(): bool
    {
        $mode = self::mode();

        if ($mode === 'first_user') {
            return User::query()->doesntExist();
        }

        if ($mode === 'open') {
            return true;
        }

        if ($mode === 'closed') {
            return false;
        }

        return filter_var(value: $mode, filter: FILTER_VALIDATE_BOOLEAN);
    }

    public static function isOpen(): bool
    {
        return self::open();
    }

    public static function needsInitialSetup(): bool
    {
        return self::mode() === 'first_user' && User::query()->doesntExist();
    }
}
