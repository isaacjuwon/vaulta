<?php

declare(strict_types=1);

use App\Models\User;

test('guests are redirected to the login page', function (): void {
    $response = $this->get(uri: route(name: 'vault'));
    $response->assertRedirect(uri: route(name: 'login'));
});

test('authenticated users can visit the vault and legacy dashboard redirects there', function (): void {
    $user = User::factory()->create();
    $this->actingAs(user: $user);

    $this->get(uri: route(name: 'dashboard'))
        ->assertRedirect(uri: route(name: 'vault'));

    $this->get(uri: route(name: 'vault'))
        ->assertOk();
});
