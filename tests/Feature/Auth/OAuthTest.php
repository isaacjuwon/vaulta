<?php

declare(strict_types=1);

use App\Actions\OAuth\LinkOAuthAccount;
use App\Actions\OAuth\RegisterOAuthUser;
use App\Actions\OAuth\SynchronizeOAuthAccount;
use App\Data\OAuth\OAuthUserData;
use App\Enums\OAuthProvider;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Config::set('services.github.client_id', 'github-client-id');
    Config::set('services.github.client_secret', 'github-client-secret');
    Config::set('services.google.client_id', 'google-client-id');
    Config::set('services.google.client_secret', 'google-client-secret');
});

test('oauth redirect initiates authorization flow with state parameter', function (): void {
    $response = $this->get(uri: route(name: 'oauth.redirect', parameters: ['provider' => 'github']));

    $response->assertRedirect();
    $targetUrl = (string) $response->headers->get(key: 'Location');

    expect($targetUrl)->toContain('https://github.com/login/oauth/authorize')
        ->and($targetUrl)->toContain('client_id=github-client-id')
        ->and($targetUrl)->toContain('state=')
        ->and(session()->has('oauth.state'))->toBeTrue()
        ->and(session('oauth.provider'))->toBe('github');
});

test('oauth redirect uses pkce for google authorization', function (): void {
    $response = $this->get(uri: route(name: 'oauth.redirect', parameters: ['provider' => 'google']));

    $response->assertRedirect();
    $targetUrl = (string) $response->headers->get(key: 'Location');

    expect($targetUrl)->toContain('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($targetUrl)->toContain('client_id=google-client-id')
        ->and($targetUrl)->toContain('code_challenge=')
        ->and($targetUrl)->toContain('code_challenge_method=S256')
        ->and(session()->has('oauth.code_verifier'))->toBeTrue();
});

test('oauth callback verifies state, creates new user, links oauth account and signs them in', function (): void {
    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response(body: [
            'access_token' => 'gho_mock_access_token_123',
            'token_type' => 'bearer',
            'scope' => 'read:user,user:email',
        ]),
        'https://api.github.com/user' => Http::response(body: [
            'id' => 987654,
            'name' => 'Octocat Developer',
            'login' => 'octocat-dev',
            'email' => 'octocat@github.local',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/987654',
        ]),
    ]);

    $state = 'valid-test-state-token';
    session()->put('oauth.state', $state);

    $response = $this->get(uri: route(name: 'oauth.callback', parameters: [
        'provider' => 'github',
        'state' => $state,
        'code' => 'mock-auth-code',
    ]));

    $response->assertRedirect(route('vault'));
    expect(Auth::check())->toBeTrue();

    /** @var User $user */
    $user = Auth::user();
    expect($user->email)->toBe('octocat@github.local')
        ->and($user->name)->toBe('Octocat Developer')
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $oauthAccount = OAuthAccount::query()
        ->where(column: 'user_id', operator: '=', value: $user->id)
        ->where(column: 'provider', operator: '=', value: OAuthProvider::GitHub)
        ->first();

    expect($oauthAccount)->not->toBeNull()
        ->and($oauthAccount?->provider_id)->toBe('987654')
        ->and($oauthAccount?->access_token)->toBe('gho_mock_access_token_123');
});

test('oauth callback links account to existing user by email', function (): void {
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'name' => 'Existing User',
    ]);

    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response(body: [
            'access_token' => 'gho_existing_token',
        ]),
        'https://api.github.com/user' => Http::response(body: [
            'id' => 112233,
            'name' => 'Existing User GitHub',
            'email' => 'existing@example.com',
        ]),
    ]);

    $state = 'test-state-existing-user';
    session()->put('oauth.state', $state);

    $response = $this->get(uri: route(name: 'oauth.callback', parameters: [
        'provider' => 'github',
        'state' => $state,
        'code' => 'auth-code-123',
    ]));

    $response->assertRedirect(route('vault'));
    expect(Auth::id())->toBe($existingUser->id);

    expect($existingUser->hasOAuthProvider(OAuthProvider::GitHub))->toBeTrue();
});

test('oauth callback connects account to currently authenticated user', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response(body: [
            'access_token' => 'gho_connected_token',
        ]),
        'https://api.github.com/user' => Http::response(body: [
            'id' => 554433,
            'login' => 'alice-connected',
            'email' => 'alice@github.local',
        ]),
    ]);

    $state = 'test-state-connect';
    session()->put('oauth.state', $state);
    session()->put('oauth.connecting_user_id', $user->id);

    $response = $this->actingAs($user)->get(uri: route(name: 'oauth.callback', parameters: [
        'provider' => 'github',
        'state' => $state,
        'code' => 'auth-code-connect',
    ]));

    $response->assertRedirect(route('settings', ['tab' => 'oauth']));

    expect($user->hasOAuthProvider(OAuthProvider::GitHub))->toBeTrue();
});

test('oauth callback rejects invalid state parameter', function (): void {
    session()->put('oauth.state', 'valid-state');

    $response = $this->get(uri: route(name: 'oauth.callback', parameters: [
        'provider' => 'github',
        'state' => 'wrong-state',
        'code' => 'code-123',
    ]));

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

test('user can unlink connected oauth account in settings hub', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    OAuthAccount::query()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'provider_id' => '12345',
        'provider_email' => 'alice@github.local',
    ]);

    expect($user->hasOAuthProvider(OAuthProvider::GitHub))->toBeTrue();

    Livewire::actingAs($user)
        ->test(name: 'pages::settings.index')
        ->set('tab', 'oauth')
        ->assertSee(values: 'Connected accounts')
        ->assertSee(values: 'alice@github.local')
        ->call('unlinkOAuth', 'github')
        ->assertHasNoErrors();

    expect($user->fresh()->hasOAuthProvider(OAuthProvider::GitHub))->toBeFalse();
});

test('user cannot unlink only oauth account if they have no password', function (): void {
    $user = User::factory()->create([
        'password' => null,
    ]);

    OAuthAccount::query()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'provider_id' => '12345',
        'provider_email' => 'alice@github.local',
    ]);

    Livewire::actingAs($user)
        ->test(name: 'pages::settings.index')
        ->set('tab', 'oauth')
        ->call('unlinkOAuth', 'github')
        ->assertHasNoErrors();

    // Still connected because unlinking is blocked for safety
    expect($user->fresh()->hasOAuthProvider(OAuthProvider::GitHub))->toBeTrue();
});

test('login and register pages render oauth component', function (): void {
    $this->get(uri: route('login'))
        ->assertOk()
        ->assertSee(value: 'Or continue with')
        ->assertSee(value: 'GitHub')
        ->assertSee(value: 'Google');

    $this->get(uri: route('register'))
        ->assertOk()
        ->assertSee(value: 'Or continue with')
        ->assertSee(value: 'GitHub')
        ->assertSee(value: 'Google');
});

test('link oauth account action connects provider and updates user avatar and verified email', function (): void {
    $user = User::factory()->create([
        'avatar' => null,
        'email_verified_at' => null,
    ]);

    $userData = new OAuthUserData(
        provider: OAuthProvider::GitHub,
        providerId: 'gh-888',
        email: 'developer@github.com',
        name: 'GitHub Dev',
        nickname: 'ghdev',
        avatar: 'https://avatars.githubusercontent.com/u/888',
        accessToken: 'gho_token_888',
        refreshToken: null,
        expiresAt: null,
    );

    $action = app(LinkOAuthAccount::class);
    $account = $action->handle(user: $user, userData: $userData, markEmailVerified: true);

    expect($account->provider_id)->toBe('gh-888')
        ->and($account->user_id)->toBe($user->id)
        ->and($user->fresh()->avatar)->toBe('https://avatars.githubusercontent.com/u/888')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('synchronize oauth account action updates tokens and profile metadata', function (): void {
    $user = User::factory()->create();
    $account = OAuthAccount::query()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::Google,
        'provider_id' => 'goog-999',
        'provider_email' => 'old@google.com',
        'access_token' => 'old_token',
    ]);

    $userData = new OAuthUserData(
        provider: OAuthProvider::Google,
        providerId: 'goog-999',
        email: 'fresh@google.com',
        name: 'Updated Google User',
        nickname: null,
        avatar: 'https://lh3.googleusercontent.com/updated',
        accessToken: 'fresh_token',
        refreshToken: 'fresh_refresh',
        expiresAt: now()->addHour(),
    );

    $action = app(SynchronizeOAuthAccount::class);
    $returnedUser = $action->handle(account: $account, userData: $userData);

    expect($returnedUser->id)->toBe($user->id)
        ->and($account->fresh()->provider_email)->toBe('fresh@google.com')
        ->and($account->fresh()->access_token)->toBe('fresh_token')
        ->and($account->fresh()->refresh_token)->toBe('fresh_refresh')
        ->and($account->fresh()->avatar)->toBe('https://lh3.googleusercontent.com/updated');
});

test('register oauth user action provisions user and marks first account as admin', function (): void {
    User::query()->delete();

    $userData = new OAuthUserData(
        provider: OAuthProvider::GitHub,
        providerId: 'gh-first-1',
        email: 'founder@vaulta.local',
        name: 'Vaulta Founder',
        nickname: 'founder',
        avatar: null,
        accessToken: 'gho_founder_token',
        refreshToken: null,
        expiresAt: null,
    );

    $action = app(RegisterOAuthUser::class);
    $user = $action->handle(userData: $userData);

    expect($user->is_admin)->toBeTrue()
        ->and($user->email)->toBe('founder@vaulta.local')
        ->and($user->password)->toBeNull()
        ->and($user->hasOAuthProvider(OAuthProvider::GitHub))->toBeTrue();
});
