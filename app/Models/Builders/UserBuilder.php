<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<User>
 */
final class UserBuilder extends Builder
{
    public function activeAdmins(): self
    {
        return $this
            ->where(column: 'is_admin', operator: '=', value: true)
            ->where(column: 'is_blocked', operator: '=', value: false);
    }

    public function blocked(): self
    {
        return $this->where(column: 'is_blocked', operator: '=', value: true);
    }

    public function notBlocked(): self
    {
        return $this->where(column: 'is_blocked', operator: '=', value: false);
    }

    public function createdSinceDays(int $days): self
    {
        return $this->where(column: 'created_at', operator: '>=', value: now()->subDays(value: $days));
    }

    public function matching(string $term): self
    {
        $trimmed = trim(string: $term);
        if ($trimmed === '') {
            return $this;
        }

        $escaped = str_replace(
            search: ['\\', '%', '_'],
            replace: ['\\\\', '\\%', '\\_'],
            subject: mb_substr(string: $trimmed, start: 0, length: 100),
        );
        $pattern = "%{$escaped}%";

        return $this->where(column: static function (self $query) use ($pattern): void {
            $query
                ->whereRaw(sql: "name LIKE ? ESCAPE '\\'", bindings: [$pattern])
                ->orWhereRaw(sql: "email LIKE ? ESCAPE '\\'", bindings: [$pattern]);
        });
    }
}
