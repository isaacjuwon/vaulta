<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Authenticator;
use App\Support\Totp\Base32;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;

#[Signature(signature: 'vaulta:check')]
#[Description(description: 'Verify every stored authenticator entry decrypts cleanly with the configured APP_KEY / APP_PREVIOUS_KEYS')]
final class CheckVaultCommand extends Command
{
    public function handle(): int
    {
        $total = 0;
        $failures = [];

        foreach (Authenticator::query()->cursor() as $entry) {
            $total++;

            try {
                $secret = $entry->secret;
                if (! Base32::isValidSecret(input: $secret)) {
                    $failures[] = "Entry #{$entry->id} ({$entry->issuer}): decrypted secret is invalid Base32.";
                }
            } catch (DecryptException) {
                $failures[] = "Entry #{$entry->id} ({$entry->issuer}): failed to decrypt secret.";
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error(string: $failure);
            }

            $this->error(string: sprintf('Vault check failed: %d of %d entries unreadable.', count($failures), $total));

            return self::FAILURE;
        }

        $this->info(string: sprintf('Vault check passed: %d entries verified.', $total));

        return self::SUCCESS;
    }
}
