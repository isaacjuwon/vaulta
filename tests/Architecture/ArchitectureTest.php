<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;

arch('app files declare strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('domain actions are final readonly classes without Livewire dependencies')
    ->expect([
        'App\Actions\Admin',
        'App\Actions\Appearance',
        'App\Actions\Auth',
        'App\Actions\Entries',
        'App\Actions\Settings',
        'App\Actions\Totp',
    ])
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveMethod('handle')
    ->not->toHaveSuffix('Action')
    ->not->toUse('Livewire');

arch('fortify actions are final readonly classes without Livewire dependencies')
    ->expect([
        CreateNewUser::class,
        ResetUserPassword::class,
    ])
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly()
    ->not->toUse('Livewire');

arch('totp support is pure php without database or model dependencies')
    ->expect('App\Support\Totp')
    ->not->toUse([
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'App\Models',
        'Livewire',
    ]);

arch('enums are string backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('eloquent builders extend base eloquent builder and are final')
    ->expect('App\Models\Builders')
    ->toBeClasses()
    ->toBeFinal();

arch('no debug helpers remain in codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
