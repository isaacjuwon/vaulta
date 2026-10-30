<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view dedicated settings hub', function (): void {
    $user = User::factory()->create();

    $this->actingAs(user: $user)
        ->get(uri: route(name: 'settings'))
        ->assertOk()
        ->assertSee(value: 'Settings')
        ->assertSee(value: 'Profile')
        ->assertSee(value: 'Password & security')
        ->assertSee(value: 'Active sessions')
        ->assertSee(value: 'Appearance')
        ->assertSee(value: 'Connected accounts');
});

test('settings hub allows switching tabs and updating profile information', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $component = Livewire::actingAs(user: $user)
        ->test(name: 'pages::settings.index')
        ->assertSet(name: 'tab', value: 'profile')
        ->assertSee(values: 'Update your name, email address, and profile photo.')
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()?->name)->toBe('Updated Name')
        ->and($user->fresh()?->email)->toBe('updated@example.com');

    // Switch to security tab
    $component->call('setTab', 'security')
        ->assertSet(name: 'tab', value: 'security')
        ->assertSee(values: 'Open security center');

    // Switch to sessions tab
    $component->call('setTab', 'sessions')
        ->assertSet(name: 'tab', value: 'sessions')
        ->assertSee(values: 'Manage active sessions');

    // Switch to appearance tab
    $component->call('setTab', 'appearance')
        ->assertSet(name: 'tab', value: 'appearance')
        ->assertSee(values: 'Customize the visual style');

    // Switch to oauth tab
    $component->call('setTab', 'oauth')
        ->assertSet(name: 'tab', value: 'oauth')
        ->assertSee(values: 'Link your third-party social accounts');
});

test('guests are redirected from settings to login', function (): void {
    $this->get(uri: route(name: 'settings'))
        ->assertRedirect(uri: route(name: 'login'));
});
