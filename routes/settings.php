<?php

declare(strict_types=1);

use App\Actions\Appearance\ChangePalette;
use App\Enums\Palette;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::middleware(['auth', 'not-blocked'])->group(callback: function (): void {
    Route::livewire(uri: 'settings', component: 'pages::settings.index')->name(name: 'settings');

    Route::livewire(uri: 'settings/profile', component: 'pages::settings.profile')->name(name: 'profile.edit');

    Route::post(uri: 'settings/palette', action: function (Request $request, ChangePalette $changePalette): JsonResponse {
        $validated = $request->validate(rules: [
            'palette' => ['required', 'string', Rule::enum(type: Palette::class)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $changePalette->handle(
            user: $user,
            palette: Palette::from(value: $validated['palette']),
        );

        return response()
            ->json(data: ['status' => 'ok', 'palette' => $validated['palette']])
            ->withCookie(cookie(
                name: 'vaulta_palette',
                value: $validated['palette'],
                minutes: 525600,
                path: '/',
                domain: null,
                secure: null,
                httpOnly: false,
                raw: false,
                sameSite: 'lax',
            ));
    })->name(name: 'settings.palette.update');
});

Route::middleware(['auth', 'verified', 'not-blocked'])->group(callback: function (): void {
    Route::livewire(uri: 'settings/sessions', component: 'pages::settings.sessions')->name(name: 'sessions.edit');
    Route::livewire(uri: 'settings/appearance', component: 'pages::settings.appearance')->name(name: 'appearance.edit');

    Route::livewire(uri: 'settings/security', component: 'pages::settings.security')
        ->middleware(['password.confirm'])
        ->name(name: 'security.edit');

    Route::get(uri: 'account/{section?}', action: function (?string $section = 'profile'): RedirectResponse {
        return match ($section) {
            'security' => redirect()->route(route: 'security.edit'),
            'sessions' => redirect()->route(route: 'sessions.edit'),
            'appearance' => redirect()->route(route: 'appearance.edit'),
            default => redirect()->route(route: 'profile.edit'),
        };
    })->whereIn(parameters: 'section', values: ['profile', 'security', 'sessions', 'appearance'])->name(name: 'account.settings');
});

Route::get(uri: '.well-known/passkey-endpoints', action: function (): JsonResponse {
    return response()->json(data: [
        'enroll' => route(name: 'security.edit'),
        'manage' => route(name: 'security.edit'),
    ]);
})->name(name: 'well-known.passkeys');
