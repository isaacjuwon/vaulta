<?php

declare(strict_types=1);

use App\Enums\Palette;
use App\Models\Authenticator;
use App\Models\User;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;

test('palette picker persists each of the 15 palettes to the user profile', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs(user: $user)->test(name: 'settings.palette-picker');

    foreach ([Palette::Mac, Palette::Miami, Palette::Lotus, Palette::FerriteLight] as $palette) {
        $component->call('selectPalette', $palette->value)
            ->assertHasNoErrors();

        expect($user->fresh()?->resolvedPalette())->toBe(expected: $palette);
    }

    expect(Palette::cases())->toHaveCount(count: 15);
});

test('all 15 theme css files exist and declare their data palette selector', function (): void {
    foreach (Palette::cases() as $palette) {
        $path = base_path(path: "resources/css/themes/{$palette->value}.css");

        expect(is_file(filename: $path))->toBeTrue()
            ->and(file_get_contents(filename: $path))->toContain(needles: "[data-palette='{$palette->value}']");
    }
});

test('security headers and csp nonce are attached to web responses', function (): void {
    $response = $this->get(uri: route(name: 'home'));

    $response->assertOk()
        ->assertHeader(headerName: 'X-Content-Type-Options', value: 'nosniff')
        ->assertHeader(headerName: 'X-Frame-Options', value: 'DENY')
        ->assertHeader(headerName: 'Referrer-Policy', value: 'strict-origin-when-cross-origin');

    expect((string) $response->headers->get(key: 'Content-Security-Policy'))
        ->toContain(needles: "default-src 'self'")
        ->toContain(needles: "'nonce-");
});

test('security headers allow ipv6 vite hot server via valid csp scheme sources without bracketed ipv6 tokens', function (): void {
    $tempHot = storage_path(path: 'framework/testing/vite.hot');
    if (! is_dir(filename: dirname(path: $tempHot))) {
        mkdir(directory: dirname(path: $tempHot), permissions: 0777, recursive: true);
    }
    file_put_contents(filename: $tempHot, data: 'http://[::1]:5173');
    $originalHot = Vite::hotFile();
    Vite::useHotFile(path: $tempHot);

    try {
        $response = $this->get(uri: route(name: 'home'));
        $csp = (string) $response->headers->get(key: 'Content-Security-Policy');

        expect($csp)
            ->not->toContain(needles: '[::1]')
            ->toContain(needles: "worker-src 'self' blob:")
            ->toContain(needles: "font-src 'self' data: http: https:")
            ->toContain(needles: "style-src 'self' 'unsafe-inline' http: https:")
            ->toContain(needles: "connect-src 'self' http: https: ws: wss:");
    } finally {
        Vite::useHotFile(path: $originalHot);
        @unlink(filename: $tempHot);
    }
});

test('blocked users cannot sign in or access protected routes', function (): void {
    $blocked = User::factory()->blocked()->create();

    $this->post(uri: route(name: 'login.store'), data: [
        'email' => $blocked->email,
        'password' => 'password',
    ])->assertSessionHasErrors(keys: ['email']);

    $this->assertGuest();

    $this->actingAs(user: $blocked)
        ->get(uri: route(name: 'vault'))
        ->assertRedirect(uri: route(name: 'login'));
});

test('theme toggle endpoint updates user palette and validates choices', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs(user: $user)
        ->postJson(uri: route(name: 'settings.palette.update'), data: ['palette' => 'miami']);

    $response->assertOk()
        ->assertJson(value: ['status' => 'ok', 'palette' => 'miami'])
        ->assertPlainCookie(cookieName: 'vaulta_palette', value: 'miami');

    expect($user->fresh()?->resolvedPalette())->toBe(expected: Palette::Miami);

    $this->actingAs(user: $user)
        ->postJson(uri: route(name: 'settings.palette.update'), data: ['palette' => 'invalid-palette'])
        ->assertUnprocessable();
});

test('login and auth pages respect guest theme cookie across requests without requiring refresh', function (): void {
    $response = $this->withUnencryptedCookie(name: 'vaulta_palette', value: 'bubblegum')
        ->get(uri: route(name: 'login'));

    $response->assertOk()
        ->assertSee(value: 'data-palette="bubblegum"', escape: false);

    expect((string) $response->getContent())->not->toMatch('/<html[^>]*class="[^"]*\bdark\b/');

    $darkResponse = $this->withUnencryptedCookie(name: 'vaulta_palette', value: 'miami')
        ->get(uri: route(name: 'login'));

    $darkResponse->assertOk()
        ->assertSee(value: 'data-palette="miami"', escape: false)
        ->assertSee(value: 'class="dark"', escape: false);
});

test('theme toggle is rendered on welcome, auth, and authenticated app headers', function (): void {
    $user = User::factory()->create();

    $welcome = $this->get(uri: route(name: 'home'));
    $welcome->assertOk()
        ->assertSee(value: 'aria-label="Select theme"', escape: false)
        ->assertSee(value: 'Themes (15)')
        ->assertDontSee(value: 'Try all 15 built-in palettes live');

    $login = $this->get(uri: route(name: 'login'));
    $login->assertOk()
        ->assertSee(value: 'aria-label="Select theme"', escape: false)
        ->assertSee(value: 'Themes (15)');

    $vault = $this->actingAs(user: $user)->get(uri: route(name: 'vault'));
    $vault->assertOk()
        ->assertSee(value: 'aria-label="Select theme"', escape: false)
        ->assertSee(value: 'Themes (15)');

    foreach (Palette::cases() as $palette) {
        $welcome->assertSee(value: $palette->label());
    }
});

test('vaulta check and grant admin artisan commands succeed', function (): void {
    $user = User::factory()->create();
    Authenticator::factory()->create(attributes: ['user_id' => $user->id]);

    $this->artisan(command: 'vaulta:check')->assertSuccessful();
    $this->artisan(command: 'vaulta:grant-admin', parameters: ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()?->is_admin)->toBeTrue();
});
