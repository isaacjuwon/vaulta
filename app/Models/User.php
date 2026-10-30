<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OAuthProvider;
use App\Enums\Palette;
use App\Models\Builders\UserBuilder;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property string|null $palette
 * @property string|null $avatar
 * @property bool $is_admin
 * @property bool $is_blocked
 * @property CarbonImmutable|null $blocked_at
 * @property CarbonImmutable|null $sign_in_locked_until
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $authenticators_count
 * @property-read int|null $oauth_accounts_count
 * @property-read Collection<int, Authenticator> $authenticators
 * @property-read Collection<int, OAuthAccount> $oauthAccounts
 */
#[Fillable(['name', 'email', 'password', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[UseEloquentBuilder(UserBuilder::class)]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'palette' => 'ferrite',
        'is_admin' => false,
        'is_blocked' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
            'sign_in_locked_until' => 'datetime',
        ];
    }

    /**
     * Resolve the user's active Palette enum safely (gracefully falling back to Ferrite on unknown strings).
     */
    public function resolvedPalette(): Palette
    {
        return Palette::resolve(value: $this->palette);
    }

    /**
     * @return HasMany<Authenticator, $this>
     */
    public function authenticators(): HasMany
    {
        return $this->hasMany(related: Authenticator::class);
    }

    /**
     * @return HasMany<OAuthAccount, $this>
     */
    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(related: OAuthAccount::class);
    }

    /**
     * Determine if the user has connected a specific OAuth provider.
     */
    public function hasOAuthProvider(OAuthProvider|string $provider): bool
    {
        $providerValue = $provider instanceof OAuthProvider ? $provider->value : $provider;

        return $this->oauthAccounts()
            ->where(column: 'provider', operator: '=', value: $providerValue)
            ->exists();
    }

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        $initials = Str::initials(value: $this->name, capitalize: true);

        return Str::length(value: $initials) > 1
            ? Str::substr(string: $initials, start: 0, length: 1).Str::substr(string: $initials, start: -1)
            : $initials;
    }

    /**
     * Determine if the user has verified their email address, taking instance policy into account.
     */
    #[Override]
    public function hasVerifiedEmail(): bool
    {
        if (! SiteSetting::requiresEmailVerification()) {
            return true;
        }

        return $this->email_verified_at !== null;
    }

    /**
     * Send the email verification notification if required by instance policy.
     */
    #[Override]
    public function sendEmailVerificationNotification(): void
    {
        if (! SiteSetting::requiresEmailVerification()) {
            return;
        }

        $this->notify(instance: new VerifyEmail);
    }
}
