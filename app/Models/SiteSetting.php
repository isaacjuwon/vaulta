<?php

declare(strict_types=1);

namespace App\Models;

use App\Settings\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $key
 * @property string|null $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table(name: 'site_settings')]
#[Fillable(['key', 'value'])]
final class SiteSetting extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var array<string, mixed> $settings */
        $settings = Cache::remember(
            key: 'site_settings.all',
            ttl: 3600,
            callback: static function (): array {
                try {
                    return self::query()->pluck(column: 'value', key: 'key')->all();
                } catch (\Throwable) {
                    return [];
                }
            },
        );

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $serialized = is_scalar(value: $value) ? (string) $value : json_encode(value: $value);

        self::query()->updateOrCreate(
            attributes: ['key' => $key],
            values: ['value' => $serialized],
        );

        SiteSettings::purge();
    }

    public static function forget(string $key): void
    {
        self::query()->where(column: 'key', operator: '=', value: $key)->delete();
        SiteSettings::purge();
    }

    public static function requiresEmailVerification(): bool
    {
        return SiteSettings::current()->requireEmailVerification;
    }

    public static function logoUrl(): ?string
    {
        return SiteSettings::current()->logoUrl;
    }

    public static function faviconUrl(): ?string
    {
        return SiteSettings::current()->faviconUrl;
    }

    public static function footerCopyright(): string
    {
        return SiteSettings::current()->copyright;
    }

    public static function footerTagline(): string
    {
        return SiteSettings::current()->tagline;
    }

    /**
     * @return array<string, string>
     */
    public static function socialLinks(): array
    {
        return SiteSettings::current()->socialLinks;
    }

    public static function description(): string
    {
        return SiteSettings::current()->description;
    }

    public static function sessionLifetime(): int
    {
        return SiteSettings::current()->sessionLifetime;
    }

    public static function maxLoginAttempts(): int
    {
        return SiteSettings::current()->maxLoginAttempts;
    }

    /**
     * @return list<array{question: string, answer: string, sort_order: int, is_published: bool}>
     */
    public static function defaultFaqs(): array
    {
        return Faq::defaults();
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(): array
    {
        return array_values(
            Faq::query()
                ->published()
                ->ordered()
                ->get()
                ->map(static fn (Faq $faq): array => [
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ])
                ->all()
        );
    }
}
