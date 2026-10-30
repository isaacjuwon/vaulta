<?php

declare(strict_types=1);

namespace App\Actions\Vault;

use App\Actions\Entries\ParseOtpAuthUri;
use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use App\Support\Totp\Base32;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class ImportVault
{
    public function __construct(
        private ParseOtpAuthUri $parseOtpAuthUri = new ParseOtpAuthUri,
    ) {}

    /**
     * Parse and import authenticators from Vaulta, Aegis, 2FAS, Bitwarden, or OTPAuth files.
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $fileContent, ?string $password = null): int
    {
        $content = trim(string: $fileContent);

        if ($content === '') {
            throw ValidationException::withMessages(messages: [
                'file' => 'The uploaded file is empty.',
            ]);
        }

        $items = $this->extractItems(rawContent: $content, password: $password);

        if (empty($items)) {
            throw ValidationException::withMessages(messages: [
                'file' => 'No valid authenticator accounts were found in this file.',
            ]);
        }

        $importedCount = 0;

        foreach ($items as $item) {
            $issuer = trim(string: $item['issuer']);
            $account = trim(string: $item['account']);
            $rawSecret = trim(string: $item['secret']);

            if ($issuer === '' || $account === '' || $rawSecret === '') {
                continue;
            }

            $normalizedSecret = Base32::normalize(input: $rawSecret);
            if (! Base32::isValidSecret(input: $normalizedSecret)) {
                continue;
            }

            $exists = Authenticator::query()
                ->ownedBy(user: $user)
                ->where(column: 'issuer', operator: '=', value: $issuer)
                ->where(column: 'account', operator: '=', value: $account)
                ->exists();

            if ($exists) {
                continue;
            }

            $color = TagColor::tryFrom(value: (string) ($item['color'] ?? '')) ?? TagColor::default();
            $isFavorite = (bool) ($item['is_favorite'] ?? false);

            $user->authenticators()->create(attributes: [
                'issuer' => $issuer,
                'account' => $account,
                'secret' => $normalizedSecret,
                'color' => $color,
                'is_favorite' => $isFavorite,
            ]);

            $importedCount++;
        }

        return $importedCount;
    }

    /**
     * @return list<array{issuer: string, account: string, secret: string, color?: ?string, is_favorite?: bool}>
     *
     * @throws ValidationException
     */
    private function extractItems(string $rawContent, ?string $password): array
    {
        try {
            $decoded = json_decode(json: $rawContent, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->extractFromUriLines(content: $rawContent);
        }

        if (! is_array(value: $decoded)) {
            return [];
        }

        // Encrypted Vaulta backup
        if (! empty($decoded['encrypted']) && $decoded['encrypted'] === true) {
            if ($password === null || trim(string: $password) === '') {
                throw ValidationException::withMessages(messages: [
                    'password' => 'This backup is password-protected. Please enter the password used when exporting.',
                ]);
            }

            $decryptedJson = $this->decryptPayload(envelope: $decoded, password: $password);

            try {
                $decoded = json_decode(json: $decryptedJson, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(messages: [
                    'password' => 'Decryption succeeded, but the inner backup structure was corrupted.',
                ]);
            }

            if (! is_array(value: $decoded)) {
                return [];
            }
        }

        // Standard Vaulta backup format
        if (isset($decoded['entries']) && is_array(value: $decoded['entries'])) {
            $items = [];
            foreach ($decoded['entries'] as $entry) {
                if (is_array(value: $entry)) {
                    $items[] = [
                        'issuer' => (string) ($entry['issuer'] ?? 'Authenticator'),
                        'account' => (string) ($entry['account'] ?? ''),
                        'secret' => (string) ($entry['secret'] ?? ''),
                        'color' => isset($entry['color']) ? (string) $entry['color'] : null,
                        'is_favorite' => (bool) ($entry['is_favorite'] ?? false),
                    ];
                }
            }

            return $items;
        }

        // 2FAS format: {"services": [{"name": "GitHub", "otp": {"account": "...", "secret": "..."}}]}
        if (isset($decoded['services']) && is_array(value: $decoded['services'])) {
            $items = [];
            foreach ($decoded['services'] as $service) {
                if (is_array(value: $service) && isset($service['otp']) && is_array(value: $service['otp'])) {
                    $items[] = [
                        'issuer' => (string) ($service['name'] ?? '2FAS Service'),
                        'account' => (string) ($service['otp']['account'] ?? ''),
                        'secret' => (string) ($service['otp']['secret'] ?? ''),
                    ];
                }
            }

            return $items;
        }

        // Aegis format: {"db": {"entries": [{"type": "totp", "name": "...", "issuer": "...", "info": {"secret": "..."}}]}}
        if (isset($decoded['db']['entries']) && is_array(value: $decoded['db']['entries'])) {
            $items = [];
            foreach ($decoded['db']['entries'] as $entry) {
                if (is_array(value: $entry) && isset($entry['info']['secret'])) {
                    $items[] = [
                        'issuer' => (string) ($entry['issuer'] ?? $entry['name'] ?? 'Aegis Service'),
                        'account' => (string) ($entry['name'] ?? ''),
                        'secret' => (string) $entry['info']['secret'],
                    ];
                }
            }

            return $items;
        }

        // Bitwarden format: {"items": [{"name": "...", "login": {"totp": "otpauth://..."}}]}
        if (isset($decoded['items']) && is_array(value: $decoded['items'])) {
            $items = [];
            foreach ($decoded['items'] as $item) {
                if (is_array(value: $item) && ! empty($item['login']['totp'])) {
                    $totpUri = (string) $item['login']['totp'];
                    try {
                        $parsed = $this->parseOtpAuthUri->handle(rawUri: $totpUri);
                        $items[] = [
                            'issuer' => $parsed->issuer !== '' ? $parsed->issuer : (string) ($item['name'] ?? 'Bitwarden'),
                            'account' => $parsed->account,
                            'secret' => $parsed->secret,
                        ];
                    } catch (ValidationException) {
                        // Skip unparseable Bitwarden URIs
                    }
                }
            }

            return $items;
        }

        return [];
    }

    /**
     * @return list<array{issuer: string, account: string, secret: string}>
     */
    private function extractFromUriLines(string $content): array
    {
        $lines = preg_split(pattern: '/\r\n|\r|\n/', subject: $content) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $trimmed = trim(string: $line);
            if (str_starts_with(haystack: $trimmed, needle: 'otpauth://')) {
                try {
                    $payload = $this->parseOtpAuthUri->handle(rawUri: $trimmed);
                    $items[] = [
                        'issuer' => $payload->issuer,
                        'account' => $payload->account,
                        'secret' => $payload->secret,
                    ];
                } catch (ValidationException) {
                    // Skip invalid lines
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $envelope
     *
     * @throws ValidationException
     */
    private function decryptPayload(array $envelope, string $password): string
    {
        $salt = base64_decode(string: (string) ($envelope['kdf']['salt'] ?? ''), strict: true);
        $iv = base64_decode(string: (string) ($envelope['iv'] ?? ''), strict: true);
        $tag = base64_decode(string: (string) ($envelope['tag'] ?? ''), strict: true);
        $ciphertext = base64_decode(string: (string) ($envelope['data'] ?? ''), strict: true);
        $iterations = max(1, (int) ($envelope['kdf']['iterations'] ?? 100000));

        if ($salt === false || $iv === false || $tag === false || $ciphertext === false) {
            throw ValidationException::withMessages(messages: [
                'file' => 'The encrypted backup payload contains corrupted Base64 parameters.',
            ]);
        }

        $key = hash_pbkdf2(
            algo: 'sha256',
            password: $password,
            salt: $salt,
            iterations: $iterations,
            length: 32,
            binary: true,
        );

        $plaintext = openssl_decrypt(
            data: $ciphertext,
            cipher_algo: 'aes-256-gcm',
            passphrase: $key,
            options: OPENSSL_RAW_DATA,
            iv: $iv,
            tag: $tag,
        );

        if ($plaintext === false) {
            throw ValidationException::withMessages(messages: [
                'password' => 'Incorrect export password or corrupted encrypted backup.',
            ]);
        }

        return $plaintext;
    }
}
