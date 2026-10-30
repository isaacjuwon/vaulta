<?php

declare(strict_types=1);

namespace App\Support\Totp;

use InvalidArgumentException;

final readonly class Totp
{
    public const int DIGITS = 6;

    public const int PERIOD = 30;

    public const string ALGORITHM = 'sha1';

    public function __construct(
        private Clock $clock = new SystemClock,
    ) {}

    /**
     * Generate a TOTP code for a Base32-encoded secret at the clock's timestamp (or explicit Unix timestamp).
     */
    public function generate(string $base32Secret, ?int $at = null): TotpCode
    {
        $timestamp = $at ?? $this->clock->timestamp();

        try {
            $key = Base32::decode(encoded: $base32Secret);
        } catch (InvalidArgumentException) {
            return new TotpCode(
                value: '------',
                remainingSeconds: self::remainingSecondsAt(timestamp: $timestamp),
                period: self::PERIOD,
            );
        }

        return self::fromBinaryKey(binaryKey: $key, at: $timestamp);
    }

    /**
     * Generate a TOTP code directly from raw key bytes (also supports RFC 6238 raw ASCII test vectors).
     */
    public static function fromBinaryKey(string $binaryKey, int $at): TotpCode
    {
        $step = intdiv(num1: $at, num2: self::PERIOD);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac(algo: self::ALGORITHM, data: $counter, key: $binaryKey, binary: true);

        $offset = ord(character: $hash[19]) & 0x0F;
        $binary = ((ord(character: $hash[$offset]) & 0x7F) << 24)
            | ((ord(character: $hash[$offset + 1]) & 0xFF) << 16)
            | ((ord(character: $hash[$offset + 2]) & 0xFF) << 8)
            | (ord(character: $hash[$offset + 3]) & 0xFF);

        $modulo = 10 ** self::DIGITS;
        $code = str_pad(
            string: (string) ($binary % $modulo),
            length: self::DIGITS,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );

        $nextStep = $step + 1;
        $nextCounter = pack('N*', 0, $nextStep);
        $nextHash = hash_hmac(algo: self::ALGORITHM, data: $nextCounter, key: $binaryKey, binary: true);

        $nextOffset = ord(character: $nextHash[19]) & 0x0F;
        $nextBinary = ((ord(character: $nextHash[$nextOffset]) & 0x7F) << 24)
            | ((ord(character: $nextHash[$nextOffset + 1]) & 0xFF) << 16)
            | ((ord(character: $nextHash[$nextOffset + 2]) & 0xFF) << 8)
            | (ord(character: $nextHash[$nextOffset + 3]) & 0xFF);

        $nextCode = str_pad(
            string: (string) ($nextBinary % $modulo),
            length: self::DIGITS,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );

        return new TotpCode(
            value: $code,
            remainingSeconds: self::remainingSecondsAt(timestamp: $at),
            period: self::PERIOD,
            nextValue: $nextCode,
        );
    }

    /**
     * Verify a 6-digit code within a ±1 step tolerance window and return the matched counter step.
     */
    public function matchingStep(string $base32Secret, string $candidateCode, ?int $at = null): ?int
    {
        $normalizedCandidate = trim(string: $candidateCode);
        if (preg_match(pattern: '/^\d{6}$/', subject: $normalizedCandidate) !== 1) {
            return null;
        }

        $timestamp = $at ?? $this->clock->timestamp();
        $currentStep = intdiv(num1: $timestamp, num2: self::PERIOD);

        foreach ([$currentStep - 1, $currentStep, $currentStep + 1] as $step) {
            $generated = $this->generate(base32Secret: $base32Secret, at: $step * self::PERIOD);
            if (hash_equals(known_string: $generated->value, user_string: $normalizedCandidate)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Seconds remaining in the active 30-second window.
     */
    public function remainingSeconds(?int $at = null): int
    {
        return self::remainingSecondsAt(timestamp: $at ?? $this->clock->timestamp());
    }

    public static function remainingSecondsAt(int $timestamp): int
    {
        $remainder = $timestamp % self::PERIOD;
        if ($remainder < 0) {
            $remainder += self::PERIOD;
        }

        return self::PERIOD - $remainder;
    }

    /**
     * Build a standard otpauth://totp/ URI.
     */
    public static function provisioningUri(string $base32Secret, string $account, string $issuer = 'Vaulta'): string
    {
        $label = rawurlencode(string: $issuer.':'.$account);
        $query = http_build_query(
            data: [
                'secret' => Base32::normalize(input: $base32Secret),
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => self::DIGITS,
                'period' => self::PERIOD,
            ],
            numeric_prefix: '',
            arg_separator: '&',
            encoding_type: PHP_QUERY_RFC3986,
        );

        return 'otpauth://totp/'.$label.'?'.$query;
    }
}
