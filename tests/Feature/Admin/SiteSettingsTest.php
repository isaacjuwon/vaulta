<?php

declare(strict_types=1);

use App\Actions\Vault\ExportVault;
use App\Enums\Palette;
use App\Events\SiteSettingsUpdated;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

afterEach(function (): void {
    if (app()->isDownForMaintenance()) {
        Artisan::call(command: 'up');
    }
});

test('non admin users cannot access admin site settings', function (): void {
    $member = User::factory()->create();

    $this->actingAs(user: $member)
        ->get(uri: route(name: 'admin.settings'))
        ->assertForbidden();
});

test('admin can view and update site settings', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs(user: $admin)
        ->get(uri: route(name: 'admin.settings'))
        ->assertOk()
        ->assertSee(value: 'Site settings & maintenance')
        ->assertSee(value: 'Instance configuration');

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings')
        ->set('siteName', 'Sovereign 2FA Vault')
        ->set('supportEmail', 'security@sovereign.local')
        ->set('issuerName', 'Sovereign')
        ->set('registrationMode', 'closed')
        ->set('defaultTheme', 'miami')
        ->set('allowPlainExport', false)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect(SiteSetting::get(key: 'site_name'))->toBe(expected: 'Sovereign 2FA Vault')
        ->and(SiteSetting::get(key: 'support_email'))->toBe(expected: 'security@sovereign.local')
        ->and(SiteSetting::get(key: 'registration_mode'))->toBe(expected: 'closed')
        ->and(Registration::open())->toBeFalse()
        ->and(Palette::default())->toBe(Palette::Miami);
});

test('plain vault export is rejected when prohibited by site setting', function (): void {
    $user = User::factory()->create();
    $exportAction = app(ExportVault::class);

    SiteSetting::set(key: 'allow_plain_export', value: false);

    expect(fn () => $exportAction->handle(user: $user, password: null))
        ->toThrow(RuntimeException::class, 'Plaintext export is disabled by instance policy. An encryption password is required.');

    SiteSetting::set(key: 'allow_plain_export', value: true);
    expect($exportAction->handle(user: $user, password: null))->toBeJson();
});

test('admin can toggle maintenance mode on and off', function (): void {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings')
        ->set('maintenanceSecret', 'staff-bypass-key')
        ->set('maintenanceMessage', 'Scheduled database upgrade.')
        ->set('maintenanceRetry', 120)
        ->call('toggleMaintenance')
        ->assertHasNoErrors();

    expect(app()->isDownForMaintenance())->toBeTrue()
        ->and(SiteSetting::get(key: 'maintenance_secret'))->toBe(expected: 'staff-bypass-key');

    // Admin routes remain accessible during maintenance
    $this->actingAs(user: $admin)
        ->get(uri: route(name: 'admin.settings'))
        ->assertOk();

    // Turn maintenance mode off
    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings')
        ->call('toggleMaintenance')
        ->assertHasNoErrors();

    expect(app()->isDownForMaintenance())->toBeFalse();
});

test('admin can inspect and delete contact submissions', function (): void {
    $admin = User::factory()->admin()->create();
    $inquiry = ContactMessage::query()->create(attributes: [
        'name' => 'Alice Dev',
        'email' => 'alice@example.com',
        'subject' => 'Feature inquiry',
        'message' => 'Love the 15 themes! Can we export encrypted keys?',
    ]);

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings')
        ->assertSee(values: 'Alice Dev')
        ->assertSee(values: 'Feature inquiry')
        ->call('deleteMessage', $inquiry->id)
        ->assertHasNoErrors();

    expect(ContactMessage::query()->where(column: 'id', operator: '=', value: $inquiry->id)->exists())->toBeFalse();
});

test('admin can configure custom branding, footer write-ups, social links, and faqs', function (): void {
    Event::fake([SiteSettingsUpdated::class]);

    $admin = User::factory()->admin()->create();

    Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings')
        ->set('siteLogo', '/uploads/branding/custom-logo.png')
        ->set('siteFavicon', '/uploads/branding/custom-fav.png')
        ->set('footerCopyright', 'Acme Auth Vault · Licensed internally')
        ->set('footerTagline', 'Enterprise Security · Zero Telemetry')
        ->set('socialGithub', 'https://github.com/example/vaulta')
        ->set('socialTwitter', 'https://x.com/acmevault')
        ->set('socialDiscord', 'https://discord.gg/acme')
        ->set('socialBluesky', 'https://bsky.app/profile/acme.bsky.social')
        ->set('newQuestion', 'Can employees sync secrets across devices?')
        ->set('newAnswer', 'Yes, by exporting encrypted JSON vaults or using passkeys.')
        ->call('addFaq')
        ->call('saveSettings')
        ->assertHasNoErrors();

    Event::assertDispatched(SiteSettingsUpdated::class);

    expect(SiteSetting::logoUrl())->toBe('/uploads/branding/custom-logo.png')
        ->and(SiteSetting::faviconUrl())->toBe('/uploads/branding/custom-fav.png')
        ->and(SiteSetting::footerCopyright())->toBe('Acme Auth Vault · Licensed internally')
        ->and(SiteSetting::footerTagline())->toBe('Enterprise Security · Zero Telemetry')
        ->and(SiteSetting::socialLinks()['github'])->toBe('https://github.com/example/vaulta')
        ->and(SiteSetting::socialLinks()['twitter'])->toBe('https://x.com/acmevault')
        ->and(SiteSetting::socialLinks()['discord'])->toBe('https://discord.gg/acme')
        ->and(SiteSetting::socialLinks()['bluesky'])->toBe('https://bsky.app/profile/acme.bsky.social');

    $faqs = Faq::query()->published()->ordered()->get();
    $found = $faqs->firstWhere('question', 'Can employees sync secrets across devices?');
    expect($found)->not->toBeNull()
        ->and($found?->answer)->toBe('Yes, by exporting encrypted JSON vaults or using passkeys.');
});

test('admin can manage faqs with reordering, deleting, and resetting defaults', function (): void {
    $admin = User::factory()->admin()->create();

    foreach (Faq::defaults() as $default) {
        Faq::query()->create($default);
    }

    $component = Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings');

    /** @var Collection<int, Faq> $initialFaqs */
    $initialFaqs = $component->get('faqs');
    $initialCount = $initialFaqs->count();
    expect($initialCount)->toBeGreaterThan(0);

    // Reorder
    $firstQuestion = $initialFaqs[0]->question;
    $component->call('moveFaqDown', 0);
    expect($component->get('faqs')[1]->question)->toBe($firstQuestion);

    // Delete
    $component->call('deleteFaq', 0);
    expect($component->get('faqs')->count())->toBe($initialCount - 1);

    // Reset defaults
    $component->call('resetDefaultFaqs');
    expect($component->get('faqs')->count())->toBe(count(Faq::defaults()));
});

test('admin header displays back button without old top tabs', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs(user: $admin)
        ->get(uri: route(name: 'admin.dashboard'))
        ->assertOk()
        ->assertSee(value: 'Back to Vault');

    $this->actingAs(user: $admin)
        ->get(uri: route(name: 'admin.settings'))
        ->assertOk()
        ->assertSee(value: 'Back to Vault');
});

test('admin can navigate settings tabs and toggle email verification requirement', function (): void {
    $admin = User::factory()->admin()->create();

    $component = Livewire::actingAs(user: $admin)
        ->test(name: 'pages::admin.settings')
        ->assertSet(name: 'tab', value: 'general')
        ->assertSee(values: 'General')
        ->assertSee(values: 'Security & Auth')
        ->assertSee(values: 'Branding & PWA')
        ->assertSee(values: 'Footer & Social')
        ->assertSee(values: 'FAQs')
        ->assertSee(values: 'Maintenance')
        ->assertSee(values: 'Inquiries');

    // Switch to security tab and toggle email verification
    $component->call('setTab', 'security')
        ->assertSet(name: 'tab', value: 'security')
        ->set('requireEmailVerification', false)
        ->set('siteDescription', 'Updated sovereign security vault')
        ->set('sessionLifetime', 240)
        ->set('maxLoginAttempts', 3)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect(SiteSetting::requiresEmailVerification())->toBeFalse()
        ->and(SiteSetting::description())->toBe('Updated sovereign security vault')
        ->and(SiteSetting::sessionLifetime())->toBe(240)
        ->and(SiteSetting::maxLoginAttempts())->toBe(3);

    // Re-enable email verification
    $component->set('requireEmailVerification', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect(SiteSetting::requiresEmailVerification())->toBeTrue();
});
