<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OAuthProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property OAuthProvider $provider
 * @property string $provider_id
 * @property string|null $provider_email
 * @property string|null $name
 * @property string|null $nickname
 * @property string|null $avatar
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $token_expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Table(name: 'oauth_accounts')]
#[Fillable([
    'user_id',
    'provider',
    'provider_id',
    'provider_email',
    'name',
    'nickname',
    'avatar',
    'access_token',
    'refresh_token',
    'token_expires_at',
])]
class OAuthAccount extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'provider' => OAuthProvider::class,
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }
}
