<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Override;

#[Signature(signature: 'vaulta:grant-admin {email : Email address of an existing user account}')]
#[Description(description: 'Grant administrator access to an existing user account')]
final class GrantAdminCommand extends Command
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->setAliases(aliases: ['keyring:grant-admin']);
    }

    public function handle(): int
    {
        $email = mb_strtolower(string: trim(string: $this->argument(key: 'email')));

        $user = User::query()->where(column: 'email', operator: '=', value: $email)->first();

        if ($user === null) {
            $this->error(string: 'No user exists with that email address. Create an account first.');

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info(string: "{$user->email} already has administrator access.");

            return self::SUCCESS;
        }

        $user->forceFill(attributes: ['is_admin' => true])->save();

        $this->info(string: "Administrator access granted to {$user->email}.");

        return self::SUCCESS;
    }
}
