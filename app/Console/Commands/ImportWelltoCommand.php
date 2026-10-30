<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Palette;
use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

#[Signature(signature: 'vaulta:import-wellto {path : Path to the source wellto.io SQLite database file}')]
#[Description(description: 'Import users and encrypted authenticators from a wellto.io SQLite database sharing the same APP_KEY')]
final class ImportWelltoCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument(key: 'path');

        if ($path === '' || ! is_file(filename: $path)) {
            $this->error(string: "SQLite file not found at: {$path}");

            return self::FAILURE;
        }

        $pdo = new PDO(dsn: 'sqlite:'.$path);
        $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);

        $usersStatement = $pdo->query(query: 'SELECT * FROM users');
        $entriesStatement = $pdo->query(query: 'SELECT * FROM authenticators');

        if ($usersStatement === false || $entriesStatement === false) {
            $this->error(string: 'Unable to read users or authenticators from the SQLite database.');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $sourceUsers */
        $sourceUsers = $usersStatement->fetchAll(PDO::FETCH_ASSOC);

        /** @var array<int, array<string, mixed>> $sourceEntries */
        $sourceEntries = $entriesStatement->fetchAll(PDO::FETCH_ASSOC);

        $importedUsers = 0;
        $importedEntries = 0;

        DB::transaction(callback: function () use ($sourceUsers, $sourceEntries, &$importedUsers, &$importedEntries): void {
            /** @var array<int, int> $userIdMap */
            $userIdMap = [];

            foreach ($sourceUsers as $row) {
                $email = mb_strtolower(string: trim(string: (string) ($row['email'] ?? '')));
                if ($email === '') {
                    continue;
                }

                $existing = User::query()->where(column: 'email', operator: '=', value: $email)->first();

                if ($existing === null) {
                    $existing = new User;
                    $existing->forceFill(attributes: [
                        'name' => (string) ($row['name'] ?? 'User'),
                        'email' => $email,
                        'password' => (string) ($row['password'] ?? ''),
                        'palette' => Palette::default()->value,
                        'avatar' => is_string($row['avatar'] ?? null) ? $row['avatar'] : null,
                        'is_admin' => (bool) ($row['is_admin'] ?? false),
                        'is_blocked' => (bool) ($row['is_blocked'] ?? false),
                        'email_verified_at' => $row['email_verified_at'] ?? now(),
                    ])->save();

                    $importedUsers++;
                }

                $sourceId = (int) ($row['id'] ?? 0);
                if ($sourceId > 0) {
                    $userIdMap[$sourceId] = $existing->id;
                }
            }

            foreach ($sourceEntries as $entryRow) {
                $oldUserId = (int) ($entryRow['user_id'] ?? 0);
                $targetUserId = $userIdMap[$oldUserId] ?? null;
                if ($targetUserId === null) {
                    continue;
                }

                $rawColor = is_string($entryRow['color'] ?? null) ? $entryRow['color'] : 'zinc';
                $color = TagColor::tryFrom(value: $rawColor) ?? TagColor::default();

                DB::table(table: (new Authenticator)->getTable())->insert(values: [
                    'user_id' => $targetUserId,
                    'issuer' => (string) ($entryRow['issuer'] ?? 'Authenticator'),
                    'account' => (string) ($entryRow['account'] ?? ''),
                    'secret' => (string) ($entryRow['secret'] ?? ''),
                    'color' => $color->value,
                    'is_favorite' => (bool) ($entryRow['is_favorite'] ?? false),
                    'created_at' => $entryRow['created_at'] ?? now(),
                    'updated_at' => $entryRow['updated_at'] ?? now(),
                ]);

                $importedEntries++;
            }
        });

        $this->info(string: sprintf('Imported %d users and %d authenticators.', $importedUsers, $importedEntries));

        return self::SUCCESS;
    }
}
