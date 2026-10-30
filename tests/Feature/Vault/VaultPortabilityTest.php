<?php

declare(strict_types=1);

use App\Actions\Vault\ExportVault;
use App\Actions\Vault\ImportVault;
use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('vault exports and imports plain json with 100 percent fidelity', function (): void {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();

    Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'GitHub',
        'account' => 'alice@example.com',
        'secret' => 'JBSWY3DPEHPK3PXP',
        'color' => TagColor::Emerald->value,
        'is_favorite' => true,
    ]);

    Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'AWS',
        'account' => 'root@example.com',
        'secret' => 'KRSXG5CTMVRXEZLU',
        'color' => TagColor::Amber->value,
        'is_favorite' => false,
    ]);

    $exporter = app(abstract: ExportVault::class);
    $plainJson = $exporter->handle(user: $user);

    expect($plainJson)->toContain('GitHub')
        ->and($plainJson)->toContain('JBSWY3DPEHPK3PXP')
        ->and($plainJson)->toContain('AWS');

    $importer = app(abstract: ImportVault::class);
    $count = $importer->handle(user: $targetUser, fileContent: $plainJson);

    expect($count)->toBe(expected: 2);

    $importedGithub = Authenticator::query()
        ->ownedBy(user: $targetUser)
        ->where(column: 'issuer', operator: '=', value: 'GitHub')
        ->firstOrFail();

    expect($importedGithub->account)->toBe(expected: 'alice@example.com')
        ->and($importedGithub->secret)->toBe(expected: 'JBSWY3DPEHPK3PXP')
        ->and($importedGithub->tagColor())->toBe(expected: TagColor::Emerald)
        ->and($importedGithub->is_favorite)->toBeTrue();
});

test('vault exports and imports encrypted json with password verification', function (): void {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();

    Authenticator::factory()->create(attributes: [
        'user_id' => $user->id,
        'issuer' => 'ProtonMail',
        'account' => 'sec@pm.me',
        'secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $exporter = app(abstract: ExportVault::class);
    $encryptedJson = $exporter->handle(user: $user, password: 'SuperSecretVaultPassword123!');

    expect($encryptedJson)->not->toContain('sec@pm.me')
        ->and($encryptedJson)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($encryptedJson)->toContain('aes-256-gcm');

    $importer = app(abstract: ImportVault::class);

    // Fails with wrong password
    expect(fn () => $importer->handle(
        user: $targetUser,
        fileContent: $encryptedJson,
        password: 'WrongPassword!',
    ))->toThrow(exception: ValidationException::class);

    // Succeeds with correct password
    $importedCount = $importer->handle(
        user: $targetUser,
        fileContent: $encryptedJson,
        password: 'SuperSecretVaultPassword123!',
    );

    expect($importedCount)->toBe(expected: 1);
    expect(Authenticator::query()->ownedBy(user: $targetUser)->count())->toBe(expected: 1);
});

test('vault imports 2fas, aegis and raw otpauth format backups', function (): void {
    $user = User::factory()->create();
    $importer = app(abstract: ImportVault::class);

    // 2FAS format
    $twoFasPayload = json_encode([
        'schemaVersion' => 4,
        'services' => [
            [
                'name' => 'Discord',
                'otp' => [
                    'account' => 'gamer#0001',
                    'secret' => 'JBSWY3DPEHPK3PXP',
                ],
            ],
        ],
    ], flags: JSON_THROW_ON_ERROR);

    $count = $importer->handle(user: $user, fileContent: $twoFasPayload);
    expect($count)->toBe(expected: 1);

    // Aegis format
    $aegisPayload = json_encode([
        'version' => 1,
        'db' => [
            'entries' => [
                [
                    'type' => 'totp',
                    'name' => 'admin@gitlab.com',
                    'issuer' => 'GitLab',
                    'info' => [
                        'secret' => 'KRSXG5CTMVRXEZLU',
                    ],
                ],
            ],
        ],
    ], flags: JSON_THROW_ON_ERROR);

    $countAegis = $importer->handle(user: $user, fileContent: $aegisPayload);
    expect($countAegis)->toBe(expected: 1);

    // Raw OTPAuth URI lines
    $uriPayload = "otpauth://totp/Cloudflare:ops%40cf.com?secret=JBSWY3DPEHPK3PXP&issuer=Cloudflare\n";
    $countUri = $importer->handle(user: $user, fileContent: $uriPayload);
    expect($countUri)->toBe(expected: 1);

    expect(Authenticator::query()->ownedBy(user: $user)->count())->toBe(expected: 3);
});
