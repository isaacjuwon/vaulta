<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateManagedUser
{
    /**
     * @throws ValidationException
     */
    public function handle(
        User $actor,
        int $targetUserId,
        string $name,
        string $email,
        bool $isAdmin,
        ?string $newPassword = null,
    ): User {
        return DB::transaction(callback: function () use ($actor, $targetUserId, $name, $email, $isAdmin, $newPassword): User {
            $activeAdminCount = User::query()
                ->activeAdmins()
                ->orderBy(column: 'id')
                ->lockForUpdate()
                ->count();

            $target = User::query()->lockForUpdate()->findOrFail(id: $targetUserId);

            if ($target->is(model: $actor) && ! $isAdmin) {
                throw ValidationException::withMessages(messages: [
                    'isAdmin' => 'You cannot remove your own administrator access.',
                ]);
            }

            if ($target->is_admin && ! $target->is_blocked && ! $isAdmin && $activeAdminCount <= 1) {
                throw ValidationException::withMessages(messages: [
                    'isAdmin' => 'At least one administrator must remain.',
                ]);
            }

            if ($newPassword !== null && $newPassword !== '' && $target->is(model: $actor)) {
                throw ValidationException::withMessages(messages: [
                    'editPassword' => 'Change your own password from Account settings.',
                ]);
            }

            $target->forceFill(attributes: [
                'name' => trim(string: $name),
                'email' => mb_strtolower(string: trim(string: $email)),
                'is_admin' => $isAdmin,
            ])->save();

            if ($newPassword !== null && $newPassword !== '') {
                $target->forceFill(attributes: [
                    'password' => $newPassword,
                    'remember_token' => Str::random(length: 60),
                ])->save();

                $this->revokeAllSessionsFor(user: $target);
            }

            return $target;
        });
    }

    private function revokeAllSessionsFor(User $user): void
    {
        $sessionTable = config(key: 'session.table', default: 'sessions');
        if (config(key: 'session.driver') === 'database' && is_string($sessionTable) && Schema::hasTable(table: $sessionTable)) {
            DB::table(table: $sessionTable)
                ->where(column: 'user_id', operator: '=', value: $user->id)
                ->delete();
        }
    }
}
