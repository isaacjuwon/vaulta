<?php

declare(strict_types=1);

use App\Enums\OAuthProvider;
use App\Models\Authenticator;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('non admin users receive 403 across all admin routes', function (): void {
    $member = User::factory()->create();

    foreach (['admin.dashboard', 'admin.users', 'admin.authenticators', 'admin.sessions'] as $routeName) {
        $this->actingAs(user: $member)
            ->get(uri: route(name: $routeName))
            ->assertForbidden();
    }
});

test('admin can manage users and cannot block or delete their own account', function (): void {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs(user: $admin)
        ->get(uri: route(name: 'admin.dashboard'))
        ->assertOk();

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.users')
        ->call('openCreateModal')
        ->set('createName', 'New Operator')
        ->set('createEmail', 'operator@vaulta.local')
        ->set('createPassword', 'StrongPass!234')
        ->set('createIsAdmin', false)
        ->call('createUser')
        ->assertHasNoErrors()
        ->call('blockUser', $member->id)
        ->assertHasNoErrors();

    expect($member->fresh()?->is_blocked)->toBeTrue()
        ->and(User::query()->where(column: 'email', operator: '=', value: 'operator@vaulta.local')->exists())->toBeTrue();

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.users')
        ->call('blockUser', $admin->id)
        ->assertHasErrors(['delete']);
});

test('admin authenticator listing redacts secrets and updates labels', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $secret = 'JBSWY3DPEHPK3PXPKRSXG5BA';

    $entry = Authenticator::factory()->create(attributes: [
        'user_id' => $owner->id,
        'issuer' => 'OldIssuer',
        'account' => 'owner@vaulta.local',
        'secret' => $secret,
    ]);

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.authenticators')
        ->assertSee(values: 'OldIssuer')
        ->assertDontSee(values: $secret)
        ->call('openEditModal', $entry->id)
        ->set('editIssuer', 'UpdatedIssuer')
        ->call('saveMetadata')
        ->assertHasNoErrors();

    expect($entry->fresh()?->issuer)->toBe(expected: 'UpdatedIssuer')
        ->and($entry->fresh()?->secret)->toBe(expected: $secret);
});

test('admin sessions listing displays database sessions', function (): void {
    config(['session.driver' => 'database']);
    $admin = User::factory()->admin()->create();

    DB::table('sessions')->insert([
        'id' => 'admin-test-session-id',
        'user_id' => $admin->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'TestBrowser/1.0',
        'payload' => serialize(['test' => true]),
        'last_activity' => time() - 120,
    ]);

    $response = $this->actingAs(user: $admin)->get(uri: route(name: 'admin.sessions'));

    $response->assertOk()
        ->assertSee(value: 'Active instance sessions')
        ->assertSee(value: '10.0.0.1')
        ->assertSee(value: 'TestBrowser/1.0');
});

test('admin can inspect and revoke managed user oauth connections', function (): void {
    $admin = User::factory()->admin()->create();
    $targetUser = User::factory()->create(attributes: [
        'name' => 'Managed Dev',
        'email' => 'dev@vaulta.local',
    ]);

    $oauth = OAuthAccount::query()->create(attributes: [
        'user_id' => $targetUser->id,
        'provider' => OAuthProvider::GitHub,
        'provider_id' => 'gh-123456',
        'provider_email' => 'dev@github.com',
        'nickname' => 'octodev',
        'name' => 'Octo Developer',
    ]);

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.users')
        ->assertSee(values: 'Managed Dev')
        ->assertSee(values: 'GitHub')
        ->call('openOAuthModal', $targetUser->id)
        ->assertSet(name: 'showOAuthModal', value: true)
        ->assertSet(name: 'managingOAuthUserId', value: $targetUser->id)
        ->assertSee(values: 'gh-123456')
        ->assertSee(values: 'dev@github.com')
        ->call('revokeOAuthAccount', $oauth->id)
        ->assertHasNoErrors();

    expect(OAuthAccount::query()->where(column: 'id', operator: '=', value: $oauth->id)->exists())->toBeFalse();
});
