<?php

declare(strict_types=1);

namespace App\Support\Totp;

use InvalidArgumentException;

final readonly class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const int MIN_SECRET_LENGTH = 16;

    public const int MAX_SECRET_LENGTH = 128;

    /**
     * Normalize user-supplied Base32 input by stripping spaces, dashes, and padding.
     */
    public static function normalize(string $input): string
    {
        $stripped = preg_replace(pattern: '/[\s=-]+/', replacement: '', subject: $input) ?? '';

        return strtoupper(string: $stripped);
    }

    /**
     * Determine whether the raw or normalized Base32 secret satisfies Vaulta's rules.
     */
    public static function isValidSecret(string $input, int $minLength = self::MIN_SECRET_LENGTH): bool
    {
        $normalized = self::normalize(input: $input);
        $length = strlen(string: $normalized);

        if ($length < $minLength || $length > self::MAX_SECRET_LENGTH) {
            return false;
        }

        if (preg_match(pattern: '/^[A-Z2-7]+$/', subject: $normalized) !== 1) {
            return false;
        }

        return self::decode(encoded: $normalized) !== '';
    }

    /**
     * Decode a RFC 4648 Base32 string into raw binary bytes.
     *
     * @throws InvalidArgumentException
     */
    public static function decode(string $encoded): string
    {
        $normalized = self::normalize(input: $encoded);

        if ($normalized === '') {
            throw new InvalidArgumentException(message: 'Base32 secret cannot be empty.');
        }

        if (preg_match(pattern: '/^[A-Z2-7]+$/', subject: $normalized) !== 1) {
            throw new InvalidArgumentException(message: 'Base32 string contains invalid characters.');
        }

        $bits = '';
        foreach (str_split(string: $normalized) as $character) {
            $position = strpos(haystack: self::ALPHABET, needle: $character);
            if ($position === false) {
                throw new InvalidArgumentException(message: 'Base32 string contains invalid characters.');
            }

            $bits .= str_pad(string: decbin(num: $position), length: 5, pad_string: '0', pad_type: STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split(string: $bits, length: 8) as $byteBits) {
            if (strlen(string: $byteBits) === 8) {
                $decoded .= chr(codepoint: ((int) bindec(binary_string: $byteBits)) & 0xFF);
            }
        }

        if ($decoded === '') {
            throw new InvalidArgumentException(message: 'Base32 secret decoded to zero bytes.');
        }

        return $decoded;
    }

    /**
     * Encode raw binary bytes into an unpadded uppercase RFC 4648 Base32 string.
     */
    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';
        foreach (str_split(string: $bytes) as $byte) {
            $bits .= str_pad(string: decbin(num: ord(character: $byte)), length: 8, pad_string: '0', pad_type: STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split(string: $bits, length: 5) as $chunk) {
            $paddedChunk = str_pad(string: $chunk, length: 5, pad_string: '0', pad_type: STR_PAD_RIGHT);
            $encoded .= self::ALPHABET[(int) bindec(binary_string: $paddedChunk)];
        }

        return $encoded;
    }

    /**
     * Generate a cryptographically random 160-bit (32-char) Base32 secret.
     */
    public static function generateSecret(int $byteLength = 20): string
    {
        $validLength = max(10, $byteLength);

        return self::encode(bytes: random_bytes(length: $validLength));
    }
}
