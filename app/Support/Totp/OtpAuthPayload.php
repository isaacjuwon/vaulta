<?php

declare(strict_types=1);

namespace App\Support\Totp;

use InvalidArgumentException;

final readonly class OtpAuthPayload
{
    public function __construct(
        public string $issuer,
        public string $account,
        public string $secret,
    ) {}

    /**
     * Parse and validate an otpauth://totp/... URI according to Vaulta & wellto.io rules.
     *
     * @throws InvalidArgumentException
     */
    public static function fromUri(string $rawUri): self
    {
        if (strlen(string: $rawUri) > 4096) {
            throw new InvalidArgumentException(message: 'Setup links must be 4,096 characters or fewer.');
        }

        $uri = parse_url(url: trim(string: $rawUri));

        if (
            ! is_array($uri)
            || strtolower(string: $uri['scheme'] ?? '') !== 'otpauth'
            || strtolower(string: $uri['host'] ?? '') !== 'totp'
        ) {
            throw new InvalidArgumentException(message: 'Paste a valid TOTP setup link starting with otpauth://totp/.');
        }

        $parameters = [];
        parse_str(string: $uri['query'] ?? '', result: $parameters);

        $rawSecret = $parameters['secret'] ?? null;
        if (! is_string($rawSecret)) {
            throw new InvalidArgumentException(message: 'This setup link does not contain a valid Base32 secret.');
        }

        $normalizedSecret = strtoupper(
            string: preg_replace(pattern: '/[\s-]+/', replacement: '', subject: $rawSecret) ?? '',
        );

        if ($normalizedSecret === '' || preg_match(pattern: '/^[A-Z2-7]{16,128}$/', subject: $normalizedSecret) !== 1) {
            throw new InvalidArgumentException(message: 'This setup link does not contain a valid Base32 secret.');
        }

        $algorithm = is_string($parameters['algorithm'] ?? null) ? strtoupper(string: $parameters['algorithm']) : 'SHA1';
        $digits = is_string($parameters['digits'] ?? null) ? $parameters['digits'] : '6';
        $period = is_string($parameters['period'] ?? null) ? $parameters['period'] : '30';

        if ($algorithm !== 'SHA1' || $digits !== '6' || $period !== '30') {
            throw new InvalidArgumentException(message: 'This link uses a code format that is not supported yet.');
        }

        $label = rawurldecode(string: ltrim(string: $uri['path'] ?? '', characters: '/'));
        $parts = array_pad(array: explode(separator: ':', string: $label, limit: 2), length: 2, value: '');
        $labelIssuer = $parts[0];
        $labelAccount = $parts[1];

        $uriIssuer = is_string($parameters['issuer'] ?? null) ? $parameters['issuer'] : $labelIssuer;
        $resolvedIssuer = trim(string: $uriIssuer);
        $resolvedAccount = trim(string: $labelAccount !== '' ? $labelAccount : $labelIssuer);

        return new self(
            issuer: mb_substr(string: $resolvedIssuer !== '' ? $resolvedIssuer : 'Authenticator', start: 0, length: 100),
            account: mb_substr(string: $resolvedAccount, start: 0, length: 180),
            secret: $normalizedSecret,
        );
    }
}
