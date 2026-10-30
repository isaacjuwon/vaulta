<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TagColor;
use App\Models\Builders\AuthenticatorBuilder;
use App\Policies\AuthenticatorPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\AuthenticatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property string $issuer
 * @property string $account
 * @property string $secret
 * @property TagColor|null $color
 * @property bool $is_favorite
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Table(name: 'authenticators')]
#[Fillable(['issuer', 'account', 'secret', 'color', 'is_favorite'])]
#[Hidden(['secret'])]
#[UseEloquentBuilder(AuthenticatorBuilder::class)]
#[UsePolicy(AuthenticatorPolicy::class)]
#[UseFactory(AuthenticatorFactory::class)]
final class Authenticator extends Model
{
    /** @use HasFactory<AuthenticatorFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => 'zinc',
        'is_favorite' => false,
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'color' => TagColor::class,
            'is_favorite' => 'boolean',
        ];
    }

    public function resolvedColor(): TagColor
    {
        return $this->color ?? TagColor::default();
    }

    public function tagColor(): TagColor
    {
        return $this->resolvedColor();
    }

    public static function initialsFor(string $issuer): string
    {
        $words = preg_split(pattern: '/\s+/', subject: trim(string: $issuer)) ?: [];
        $letters = '';

        foreach (array_slice(array: $words, offset: 0, length: 2) as $word) {
            if ($word !== '') {
                $letters .= mb_strtoupper(string: mb_substr(string: $word, start: 0, length: 1));
            }
        }

        return $letters !== '' ? $letters : 'A';
    }

    public function issuerInitials(): string
    {
        return self::initialsFor(issuer: $this->issuer);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }
}
