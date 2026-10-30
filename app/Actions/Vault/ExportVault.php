<?php

declare(strict_types=1);

namespace App\Actions\Vault;

use App\Models\Authenticator;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final readonly class ExportVault
{
    private const int PBKDF2_ITERATIONS = 100000;

    /**
     * Export all user authenticators as encrypted or plaintext JSON.
     */
    public function handle(User $user, ?string $password = null): string
    {
        $entries = Authenticator::query()
            ->ownedBy(user: $user)
            ->sortedBy(sort: 'issuer')
            ->get();

        $plainData = [
            'vaulta_version' => 1,
            'encrypted' => false,
            'exported_at' => Carbon::now()->toIso8601String(),
            'entries' => $entries->map(callback: static fn (Authenticator $entry): array => [
                'issuer' => $entry->issuer,
                'account' => $entry->account,
                'secret' => $entry->secret,
                'color' => $entry->tagColor()->value,
                'is_favorite' => $entry->is_favorite,
                'algorithm' => 'SHA1',
                'digits' => 6,
                'period' => 30,
            ])->all(),
        ];

        $jsonPayload = json_encode(value: $plainData, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $allowPlain = (bool) (SiteSetting::get(key: 'allow_plain_export') ?? true);

        if ($password === null || trim(string: $password) === '') {
            if (! $allowPlain) {
                throw new RuntimeException(message: 'Plaintext export is disabled by instance policy. An encryption password is required.');
            }

            return $jsonPayload;
        }

        return $this->encryptPayload(payload: $jsonPayload, password: $password);
    }

    private function encryptPayload(string $payload, string $password): string
    {
        $salt = random_bytes(length: 16);
        $iv = random_bytes(length: 12);
        $tag = '';

        $key = hash_pbkdf2(
            algo: 'sha256',
            password: $password,
            salt: $salt,
            iterations: self::PBKDF2_ITERATIONS,
            length: 32,
            binary: true,
        );

        $ciphertext = openssl_encrypt(
            data: $payload,
            cipher_algo: 'aes-256-gcm',
            passphrase: $key,
            options: OPENSSL_RAW_DATA,
            iv: $iv,
            tag: $tag,
        );

        if ($ciphertext === false) {
            throw new RuntimeException(message: 'Failed to encrypt vault backup export.');
        }

        $envelope = [
            'vaulta_version' => 1,
            'encrypted' => true,
            'cipher' => 'aes-256-gcm',
            'kdf' => [
                'algorithm' => 'pbkdf2',
                'hash' => 'sha256',
                'iterations' => self::PBKDF2_ITERATIONS,
                'salt' => base64_encode(string: $salt),
            ],
            'iv' => base64_encode(string: $iv),
            'tag' => base64_encode(string: $tag),
            'data' => base64_encode(string: $ciphertext),
        ];

        return json_encode(value: $envelope, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
