<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

test('about page renders successfully and displays non-technical markdown content', function (): void {
    $response = $this->get(uri: route(name: 'about'));

    $response->assertOk()
        ->assertSee(value: 'What is Two-Factor Authentication?')
        ->assertSee(value: 'Why Choose Vaulta?')
        ->assertSee(value: 'How It Works')
        ->assertSee(value: 'Free &amp; Open', escape: false)
        ->assertDontSee(value: '15 Tactile Ferrite Palettes');
});

test('privacy and terms pages render markdown documents from md folder', function (): void {
    $privacy = $this->get(uri: route(name: 'privacy'));
    $privacy->assertOk()
        ->assertSee(value: 'Zero External Telemetry')
        ->assertSee(value: 'Cryptographic Storage &amp; Secrets', escape: false)
        ->assertSee(value: 'Data Ownership &amp; Deletion', escape: false);

    $terms = $this->get(uri: route(name: 'terms'));
    $terms->assertOk()
        ->assertSee(value: 'Terms of Service &amp; Use Policy', escape: false)
        ->assertSee(value: 'GNU Affero General Public License');
});

test('contact page renders and pre-fills details for authenticated users', function (): void {
    $guestResponse = $this->get(uri: route(name: 'contact'));
    $guestResponse->assertOk()
        ->assertSee(value: 'Contact & support');

    $user = User::factory()->create([
        'name' => 'Bob Operator',
        'email' => 'bob@vaulta.local',
    ]);

    Livewire::actingAs(user: $user)
        ->test(name: 'pages::contact')
        ->assertSet(name: 'name', value: 'Bob Operator')
        ->assertSet(name: 'email', value: 'bob@vaulta.local');
});

test('contact page submits inquiries and validates input', function (): void {
    Livewire::test(name: 'pages::contact')
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('subject', '')
        ->set('message', 'short')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'subject', 'message']);

    Livewire::test(name: 'pages::contact')
        ->set('name', 'Carol Tester')
        ->set('email', 'carol@vaulta.test')
        ->set('subject', 'Security question')
        ->set('message', 'Are recovery codes hashed with argon2 or bcrypt?')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet(name: 'sent', value: true);

    expect(ContactMessage::query()->where(column: 'email', operator: '=', value: 'carol@vaulta.test')->exists())->toBeTrue();
});

test('faq page renders successfully and allows search filtering', function (): void {
    foreach (Faq::defaults() as $default) {
        Faq::query()->create($default);
    }

    $response = $this->get(uri: route(name: 'faq'));

    $response->assertOk()
        ->assertSee(value: 'Frequently Asked Questions')
        ->assertSee(value: 'What is Vaulta?')
        ->assertSee(value: 'Can I use Vaulta offline?');

    Livewire::test(name: 'pages::faq')
        ->set('search', 'offline')
        ->assertSee('Can I use Vaulta offline?')
        ->assertDontSee('How can I import or export my accounts?');
});

test('sitemap xml includes about, privacy, terms, contact, and faq routes', function (): void {
    $response = $this->get(uri: route(name: 'sitemap'));

    $response->assertOk()
        ->assertSee(value: route(name: 'about'))
        ->assertSee(value: route(name: 'faq'))
        ->assertSee(value: route(name: 'privacy'))
        ->assertSee(value: route(name: 'terms'))
        ->assertSee(value: route(name: 'contact'));
});

test('pwa manifest and service worker assets exist and are valid', function (): void {
    $manifestPath = public_path(path: 'manifest.webmanifest');
    expect(File::exists(path: $manifestPath))->toBeTrue();

    /** @var array<string, mixed> $data */
    $data = json_decode(json: (string) File::get(path: $manifestPath), associative: true);
    expect($data['name'])->toContain('Authenticator')
        ->and($data['display'])->toBe('standalone');

    expect(File::exists(path: public_path(path: 'icon-192x192.png')))->toBeTrue()
        ->and(File::exists(path: public_path(path: 'icon-512x512.png')))->toBeTrue()
        ->and(File::exists(path: public_path(path: 'sw.js')))->toBeTrue();
});
