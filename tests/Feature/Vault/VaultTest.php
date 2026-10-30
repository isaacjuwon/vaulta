<?php

declare(strict_types=1);

use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('vault adds authenticators via manual base32 and otpauth uri and encrypts secrets at rest', function (): void {
    $user = User::factory()->create();
    $rawSecret = 'JBSWY3DPEHPK3PXP';

    Livewire::actingAs(user: $user)
        ->test(name: 'vault.add-entry')
        ->assertSee(values: 'Account icon')
        ->assertSee(values: 'GitHub')
        ->assertSee(values: 'Google')
        ->assertSee(values: 'AWS')
        ->call('selectProvider', 'GitHub')
        ->assertSet(name: 'issuer', value: 'GitHub')
        ->set('setupUri', 'otpauth://totp/GitHub:alice%40example.com?secret='.$rawSecret.'&issuer=GitHub')
        ->call('parseSetupUri')
        ->assertSet('issuer', 'GitHub')
        ->assertSet('account', 'alice@example.com')
        ->set('color', TagColor::Emerald->value)
        ->call('save')
        ->assertHasNoErrors();

    $entry = Authenticator::query()->ownedBy(user: $user)->firstOrFail();
    $rawCiphertext = DB::table(table: 'authenticators')
        ->where(column: 'id', operator: '=', value: $entry->id)
        ->value(column: 'secret');

    expect($entry->issuer)->toBe(expected: 'GitHub')
        ->and($entry->account)->toBe(expected: 'alice@example.com')
        ->and($entry->tagColor())->toBe(expected: TagColor::Emerald)
        ->and($entry->secret)->toBe(expected: $rawSecret)
        ->and($rawCiphertext)->not->toBe(expected: $rawSecret)
        ->and($entry->toArray())->not->toHaveKey(key: 'secret');
});

test('entry list enforces cross user isolation and never leaks secrets in livewire payload', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $secret = 'JBSWY3DPEHPK3PXPKRSXG5BA';
    $aliceEntry = Authenticator::factory()->create(attributes: [
        'user_id' => $alice->id,
        'issuer' => 'Fastmail_100%',
        'account' => 'alice@vaulta.local',
        'secret' => $secret,
    ]);

    $bobEntry = Authenticator::factory()->create(attributes: [
        'user_id' => $bob->id,
        'issuer' => 'Stripe',
        'account' => 'bob@vaulta.local',
    ]);

    $component = Livewire::actingAs(user: $alice)
        ->test(name: 'vault.entry-list')
        ->assertSee(values: 'Fastmail_100%')
        ->assertDontSee(values: 'Stripe')
        ->assertDontSee(values: $secret);

    $component
        ->set('search', '100%')
        ->assertSee(values: 'Fastmail_100%')
        ->call('setFavorite', $aliceEntry->id, true)
        ->call('setColor', $aliceEntry->id, TagColor::Violet->value)
        ->call('refreshCodes');

    expect($aliceEntry->fresh()?->is_favorite)->toBeTrue()
        ->and($aliceEntry->fresh()?->tagColor())->toBe(expected: TagColor::Violet);

    Livewire::actingAs(user: $alice)
        ->test(name: 'vault.entry-list')
        ->call('remove', $bobEntry->id)
        ->assertNotFound();

    expect($bobEntry->fresh())->not->toBeNull();
});

test('entry list filters can be set and cleared without client expression errors', function (): void {
    $user = User::factory()->create();

    Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'GitHub',
        'account' => 'dev@example.com',
        'is_favorite' => false,
        'color' => TagColor::Emerald->value,
    ]);

    Livewire::actingAs(user: $user)
        ->test(name: 'vault.entry-list', params: [
            'filter' => 'Favorites',
            'colorFilter' => 'sky',
        ])
        ->assertSee(values: 'No favorites match that search.')
        ->assertSeeHtml(values: 'wire:click="clearFilters"')
        ->assertDontSeeHtml(values: '$set(')
        ->call('clearFilters')
        ->assertSet(name: 'search', value: '')
        ->assertSet(name: 'colorFilter', value: '')
        ->call('setFilter', 'All accounts')
        ->assertSet(name: 'filter', value: 'All accounts')
        ->assertSee(values: 'GitHub');
});

test('issuer icon component renders svg for known brand and initials for unknown brand', function (): void {
    $renderedGithub = Blade::render('<x-ui.issuer-icon issuer="GitHub" />');
    expect($renderedGithub)->toContain('<svg')
        ->and($renderedGithub)->toContain('viewBox="0 0 24 24"');

    $renderedUnknown = Blade::render('<x-ui.issuer-icon issuer="Custom LDAP" />');
    expect($renderedUnknown)->not->toContain('<svg')
        ->and($renderedUnknown)->toContain('CL');
});

test('icon picker sfc opens modal filters presets and communicates selection to parent', function (): void {
    $user = User::factory()->create();

    // Direct child SFC test
    Livewire::actingAs(user: $user)
        ->test(name: 'vault.icon-picker', params: [
            'value' => '',
        ])
        ->assertSet(name: 'showModal', value: false)
        ->call('openModal')
        ->assertSet(name: 'showModal', value: true)
        ->set('search', 'git')
        ->assertSee(values: 'GitHub')
        ->assertSee(values: 'GitLab')
        ->assertDontSee(values: 'Bitbucket')
        ->assertDontSee(values: 'Discord')
        ->call('selectIcon', 'GitHub')
        ->assertDispatched(event: 'icon-selected', provider: 'GitHub')
        ->assertSet(name: 'value', value: 'GitHub')
        ->assertSet(name: 'showModal', value: false);

    // Parent communication test via event listener
    Livewire::actingAs(user: $user)
        ->test(name: 'vault.add-entry')
        ->dispatch(event: 'icon-selected', provider: 'Discord')
        ->assertSet(name: 'issuer', value: 'Discord');
});

test('entry list allows editing account details and updates record', function (): void {
    $user = User::factory()->create();

    $entry = Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'OldIssuer',
        'account' => 'old@example.com',
        'color' => TagColor::Zinc->value,
    ]);

    Livewire::actingAs(user: $user)
        ->test(name: 'vault.entry-list')
        ->call('openEditModal', $entry->id)
        ->assertSet(name: 'showEditModal', value: true)
        ->assertSet(name: 'editIssuer', value: 'OldIssuer')
        ->assertSet(name: 'editAccount', value: 'old@example.com')
        ->set('editIssuer', 'NewRenamedIssuer')
        ->set('editAccount', 'new@example.com')
        ->call('setEditColor', TagColor::Violet->value)
        ->call('saveEdit')
        ->assertSet(name: 'showEditModal', value: false);

    expect($entry->fresh()?->issuer)->toBe(expected: 'NewRenamedIssuer')
        ->and($entry->fresh()?->account)->toBe(expected: 'new@example.com')
        ->and($entry->fresh()?->tagColor())->toBe(expected: TagColor::Violet);
});

test('entry list handles export and import workflows via livewire actions', function (): void {
    $user = User::factory()->create();

    Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'ExportableAccount',
        'account' => 'export@example.com',
        'secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $component = Livewire::actingAs(user: $user)
        ->test(name: 'vault.entry-list')
        ->call('openExportModal')
        ->assertSet(name: 'showExportModal', value: true)
        ->call('downloadExport');

    $component->assertSet(name: 'showExportModal', value: false);

    $backupPayload = json_encode([
        'entries' => [
            [
                'issuer' => 'ImportedViaLivewire',
                'account' => 'imported@example.com',
                'secret' => 'KRSXG5CTMVRXEZLU',
                'color' => 'sky',
            ],
        ],
    ], flags: JSON_THROW_ON_ERROR);

    $component
        ->call('openImportModal')
        ->assertSet(name: 'showImportModal', value: true)
        ->set('importContent', $backupPayload)
        ->call('executeImport')
        ->assertSet(name: 'showImportModal', value: false);

    expect(Authenticator::query()->ownedBy(user: $user)->where(column: 'issuer', operator: '=', value: 'ImportedViaLivewire')->exists())->toBeTrue();
});

test('dedicated authenticator vault page renders with mobile slide out drawer and desktop spread filters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs(user: $user)->get(uri: route(name: 'vault'));

    $response->assertOk()
        ->assertSee(value: 'Your codes, in sync.')
        ->assertSee(value: 'Add account')
        ->assertSee(value: 'Open filters')
        ->assertSee(value: 'Filters &amp; Sorting', escape: false)
        ->assertSee(value: 'All accounts')
        ->assertSee(value: 'Favorites');
});

test('entry list tracks active filter counts and supports custom sort and per page methods', function (): void {
    $user = User::factory()->create();

    Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'GitHub',
        'account' => 'alice@example.com',
    ]);

    $component = Livewire::actingAs(user: $user)
        ->test(name: 'vault.entry-list');

    expect($component->instance()->hasActiveFilters())->toBeFalse()
        ->and($component->instance()->activeFilterCount())->toBe(0);

    $component
        ->set('search', 'Git')
        ->call('setSort', 'recent')
        ->call('setPerPage', 24);

    expect($component->instance()->hasActiveFilters())->toBeTrue()
        ->and($component->instance()->activeFilterCount())->toBe(2)
        ->and($component->get('perPage'))->toBe(24)
        ->and($component->get('sort'))->toBe('recent');

    $component->call('clearSearch');
    expect($component->get('search'))->toBe('');
});
