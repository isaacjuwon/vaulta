<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class BlockManagedUser
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, int $targetUserId): bool
    {
        return DB::transaction(callback: function () use ($actor, $targetUserId): bool {
            $activeAdminCount = User::query()
                ->activeAdmins()
                ->lockForUpdate()
                ->count();

            $target = User::query()->lockForUpdate()->findOrFail(id: $targetUserId);

            if ($target->is(model: $actor)) {
                throw ValidationException::withMessages(messages: [
                    'delete' => 'You cannot block your own administrator account.',
                ]);
            }

            if ($target->is_admin && ! $target->is_blocked && $activeAdminCount <= 1) {
                throw ValidationException::withMessages(messages: [
                    'delete' => 'The last active administrator cannot be blocked.',
                ]);
            }

            if ($target->is_blocked) {
                return false;
            }

            $target->forceFill(attributes: [
                'is_blocked' => true,
                'blocked_at' => now(),
                'remember_token' => Str::random(length: 60),
            ])->save();

            $sessionTable = config(key: 'session.table', default: 'sessions');
            if (config(key: 'session.driver') === 'database' && is_string($sessionTable) && Schema::hasTable(table: $sessionTable)) {
                DB::table(table: $sessionTable)
                    ->where(column: 'user_id', operator: '=', value: $target->id)
                    ->delete();
            }

            return true;
        });
    }
}
