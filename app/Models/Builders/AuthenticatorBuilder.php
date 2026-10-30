<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Authenticator>
 */
final class AuthenticatorBuilder extends Builder
{
    public function ownedBy(User $user): self
    {
        return $this->where(column: 'user_id', operator: '=', value: $user->getKey());
    }

    public function favoritesOnly(): self
    {
        return $this->where(column: 'is_favorite', operator: '=', value: true);
    }

    public function favoritesFirst(): self
    {
        return $this
            ->orderByDesc(column: 'is_favorite')
            ->orderBy(column: 'issuer')
            ->orderBy(column: 'account');
    }

    public function sortedBy(string $sort): self
    {
        return match ($sort) {
            'recent' => $this->orderByDesc(column: 'created_at')->orderByDesc(column: 'id'),
            'favorites' => $this->favoritesFirst(),
            default => $this->orderBy(column: 'issuer')->orderBy(column: 'account'),
        };
    }

    public function matching(string $term): self
    {
        $trimmed = trim(string: $term);
        if ($trimmed === '') {
            return $this;
        }

        $escaped = $this->escapeLike(value: mb_substr(string: $trimmed, start: 0, length: 100));
        $pattern = "%{$escaped}%";

        return $this->where(column: static function (self $query) use ($pattern): void {
            $query
                ->whereRaw(sql: "issuer LIKE ? ESCAPE '\\'", bindings: [$pattern])
                ->orWhereRaw(sql: "account LIKE ? ESCAPE '\\'", bindings: [$pattern]);
        });
    }

    public function matchingAdminSearch(string $term): self
    {
        $trimmed = trim(string: $term);
        if ($trimmed === '') {
            return $this;
        }

        $escaped = $this->escapeLike(value: mb_substr(string: $trimmed, start: 0, length: 100));
        $pattern = "%{$escaped}%";

        return $this->where(column: static function (self $query) use ($pattern): void {
            $query
                ->whereRaw(sql: "issuer LIKE ? ESCAPE '\\'", bindings: [$pattern])
                ->orWhereRaw(sql: "account LIKE ? ESCAPE '\\'", bindings: [$pattern])
                ->orWhereHas(
                    relation: 'user',
                    callback: static function (UserBuilder $userQuery) use ($pattern): void {
                        $userQuery
                            ->whereRaw(sql: "name LIKE ? ESCAPE '\\'", bindings: [$pattern])
                            ->orWhereRaw(sql: "email LIKE ? ESCAPE '\\'", bindings: [$pattern]);
                    },
                );
        });
    }

    public function coloured(TagColor $color): self
    {
        return $this->where(column: 'color', operator: '=', value: $color->value);
    }

    public function withoutSecrets(): self
    {
        return $this->select(columns: [
            'id',
            'user_id',
            'issuer',
            'account',
            'color',
            'is_favorite',
            'created_at',
            'updated_at',
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            search: ['\\', '%', '_'],
            replace: ['\\\\', '\\%', '\\_'],
            subject: $value,
        );
    }
}
