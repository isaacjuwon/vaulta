<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final readonly class DeleteManagedUser
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, int $targetUserId): void
    {
        DB::transaction(callback: function () use ($actor, $targetUserId): void {
            $activeAdminCount = User::query()
                ->activeAdmins()
                ->orderBy(column: 'id')
                ->lockForUpdate()
                ->count();

            $target = User::query()->lockForUpdate()->findOrFail(id: $targetUserId);

            if ($target->is(model: $actor)) {
                throw ValidationException::withMessages(messages: [
                    'delete' => 'You cannot delete your own administrator account.',
                ]);
            }

            if ($target->is_admin && ! $target->is_blocked && $activeAdminCount <= 1) {
                throw ValidationException::withMessages(messages: [
                    'delete' => 'The last administrator cannot be deleted.',
                ]);
            }

            $sessionTable = config(key: 'session.table', default: 'sessions');
            if (config(key: 'session.driver') === 'database' && is_string($sessionTable) && Schema::hasTable(table: $sessionTable)) {
                DB::table(table: $sessionTable)
                    ->where(column: 'user_id', operator: '=', value: $target->id)
                    ->delete();
            }

            $target->delete();
        });
    }
}
