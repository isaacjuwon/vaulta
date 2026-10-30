<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class RevokeUserSession
{
    public function handle(User $user, string $token, string $currentSessionId): bool
    {
        if (preg_match(pattern: '/^[a-f0-9]{64}$/', subject: $token) !== 1) {
            return false;
        }

        $sessionTable = $this->sessionTable();
        if ($sessionTable === null) {
            return false;
        }

        $sessions = DB::table(table: $sessionTable)
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->where(column: 'id', operator: '!=', value: $currentSessionId)
            ->get(columns: ['id']);

        foreach ($sessions as $session) {
            $sessionId = is_string($session->id ?? null) ? $session->id : '';
            if ($sessionId !== '' && hash_equals(known_string: self::tokenFor(sessionId: $sessionId), user_string: $token)) {
                $user->forceFill(attributes: [
                    'remember_token' => Str::random(length: 60),
                ])->save();

                DB::table(table: $sessionTable)
                    ->where(column: 'id', operator: '=', value: $sessionId)
                    ->delete();

                return true;
            }
        }

        return false;
    }

    public static function tokenFor(string $sessionId): string
    {
        $appKey = config(key: 'app.key');

        return hash_hmac(
            algo: 'sha256',
            data: $sessionId,
            key: is_string($appKey) ? $appKey : '',
        );
    }

    private function sessionTable(): ?string
    {
        if (config(key: 'session.driver') !== 'database') {
            return null;
        }

        $table = config(key: 'session.table', default: 'sessions');
        if (! is_string($table) || ! Schema::hasTable(table: $table)) {
            return null;
        }

        return $table;
    }
}
