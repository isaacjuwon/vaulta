<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class RevokeOtherUserSessions
{
    public function handle(User $user, string $currentSessionId): int
    {
        if (config(key: 'session.driver') !== 'database') {
            return 0;
        }

        $table = config(key: 'session.table', default: 'sessions');
        if (! is_string($table) || ! Schema::hasTable(table: $table)) {
            return 0;
        }

        $user->forceFill(attributes: [
            'remember_token' => Str::random(length: 60),
        ])->save();

        return DB::table(table: $table)
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->where(column: 'id', operator: '!=', value: $currentSessionId)
            ->delete();
    }
}
