<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Vault\ExportVault;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

#[Signature(signature: 'vaulta:backup {--user= : Optional email of a specific user to backup} {--password= : Optional password to encrypt the backup with AES-256-GCM} {--output= : Custom destination path}')]
#[Description(description: 'Create an automated portable JSON backup of vault authenticators')]
final class BackupVaultCommand extends Command
{
    public function handle(ExportVault $exportVault): int
    {
        $userEmail = $this->option(key: 'user');
        $password = $this->option(key: 'password');
        $outputPath = $this->option(key: 'output');

        $query = User::query();
        if ($userEmail !== null && trim(string: (string) $userEmail) !== '') {
            $query->where(column: 'email', operator: '=', value: (string) $userEmail);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error(string: 'No matching users found to backup.');

            return self::FAILURE;
        }

        $backupDir = storage_path(path: 'app/backups');
        if (! File::isDirectory(directory: $backupDir)) {
            File::makeDirectory(path: $backupDir, mode: 0755, recursive: true);
        }

        $timestamp = Carbon::now()->format(format: 'Y-m-d-His');

        foreach ($users as $user) {
            $json = $exportVault->handle(
                user: $user,
                password: is_string(value: $password) && $password !== '' ? $password : null,
            );

            $destination = is_string(value: $outputPath) && $outputPath !== ''
                ? $outputPath
                : $backupDir.DIRECTORY_SEPARATOR.sprintf('backup-%s-%s.json', $user->id, $timestamp);

            File::put(path: $destination, contents: $json);

            $this->info(string: sprintf(
                'Backed up user %s to: %s (%s)',
                $user->email,
                $destination,
                $password ? 'encrypted' : 'plaintext',
            ));
        }

        return self::SUCCESS;
    }
}
