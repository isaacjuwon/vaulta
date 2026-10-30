<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Settings\RevokeUserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class RevokeManagedSession
{
    public function handle(string $token, string $currentSessionId): bool
    {
        if (preg_match(pattern: '/^[a-f0-9]{64}$/', subject: $token) !== 1 || config(key: 'session.driver') !== 'database') {
            return false;
        }

        $sessionTable = config(key: 'session.table', default: 'sessions');
        if (! is_string($sessionTable) || ! Schema::hasTable(table: $sessionTable)) {
            return false;
        }

        $sessions = DB::table(table: $sessionTable)
            ->where(column: 'id', operator: '!=', value: $currentSessionId)
            ->get(columns: ['id', 'user_id']);

        foreach ($sessions as $session) {
            $sessionId = is_string($session->id ?? null) ? $session->id : '';
            if ($sessionId !== '' && hash_equals(known_string: RevokeUserSession::tokenFor(sessionId: $sessionId), user_string: $token)) {
                $userId = $session->user_id ?? null;
                if (is_int($userId) || is_string($userId)) {
                    DB::table(table: 'users')
                        ->where(column: 'id', operator: '=', value: $userId)
                        ->update(values: ['remember_token' => Str::random(length: 60)]);
                }

                DB::table(table: $sessionTable)
                    ->where(column: 'id', operator: '=', value: $sessionId)
                    ->delete();

                return true;
            }
        }

        return false;
    }
}
