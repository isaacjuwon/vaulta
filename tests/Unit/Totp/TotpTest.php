<?php

declare(strict_types=1);

use App\Support\Totp\Base32;
use App\Support\Totp\FrozenClock;
use App\Support\Totp\OtpAuthPayload;
use App\Support\Totp\Totp;

test('totp matches rfc 6238 sha1 appendix b vectors truncated to 6 digits', function (int $timestamp, string $expectedSixDigits, int $expectedRemaining): void {
    $code = Totp::fromBinaryKey(binaryKey: '12345678901234567890', at: $timestamp);

    expect($code->value)->toBe(expected: $expectedSixDigits)
        ->and($code->remainingSeconds)->toBe(expected: $expectedRemaining);
})->with([
    't=59' => [59, '287082', 1],
    't=1111111109' => [1111111109, '081804', 1],
    't=1111111111' => [1111111111, '050471', 29],
    't=1234567890' => [1234567890, '005924', 30],
    't=2000000000' => [2000000000, '279037', 10],
    't=20000000000' => [20000000000, '353130', 10],
]);

test('totp uses injected frozen clock deterministically', function (): void {
    $secret = Base32::encode(bytes: '12345678901234567890');
    $totp = new Totp(clock: new FrozenClock(frozenAt: 59));

    $code = $totp->generate(base32Secret: $secret);

    expect($code->value)->toBe(expected: '287082')
        ->and($code->formatted())->toBe(expected: '287 082')
        ->and($totp->matchingStep(base32Secret: $secret, candidateCode: '287082'))->toBe(expected: 1);
});

test('base32 normalizes spaces dashes and padding and rejects short secrets', function (): void {
    $raw = 'jbsw y3dp-ehpk-3pxp====';

    expect(Base32::normalize(input: $raw))->toBe(expected: 'JBSWY3DPEHPK3PXP')
        ->and(Base32::isValidSecret(input: $raw))->toBeTrue()
        ->and(Base32::isValidSecret(input: 'JBSWY3DP'))->toBeFalse();
});

test('otpauth payload parses standard totp uri', function (): void {
    $payload = OtpAuthPayload::fromUri(
        rawUri: 'otpauth://totp/GitHub:ops%40vaulta.local?secret=JBSWY3DPEHPK3PXP&issuer=GitHub&algorithm=SHA1&digits=6&period=30',
    );

    expect($payload->issuer)->toBe(expected: 'GitHub')
        ->and($payload->account)->toBe(expected: 'ops@vaulta.local')
        ->and($payload->secret)->toBe(expected: 'JBSWY3DPEHPK3PXP');
});

test('totp generates upcoming next code for seamless transitions', function (): void {
    $clock = new FrozenClock(frozenAt: 59);
    $totp = new Totp(clock: $clock);

    $code = $totp->generate(base32Secret: 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
    $nextCode = (new Totp(clock: new FrozenClock(frozenAt: 59 + 30)))
        ->generate(base32Secret: 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

    expect($code->nextValue)->not->toBeNull()
        ->and($code->nextValue)->toBe(expected: $nextCode->value)
        ->and($code->formattedNext())->toBe(expected: $nextCode->formatted());
});
