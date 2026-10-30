<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property int $sort_order
 * @property bool $is_published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static> ordered()
 * @method static Builder<static> published()
 */
#[Table(name: 'faqs')]
#[Fillable(['question', 'answer', 'sort_order', 'is_published'])]
#[UseFactory(FaqFactory::class)]
final class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy(column: 'sort_order')->orderBy(column: 'id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(column: 'is_published', operator: '=', value: true);
    }

    /**
     * Curated starter questions for initialization.
     *
     * @return list<array{question: string, answer: string, sort_order: int, is_published: bool}>
     */
    public static function defaults(): array
    {
        return [
            [
                'question' => 'What is Vaulta?',
                'answer' => 'Vaulta is a self-hosted, private two-factor authenticator (TOTP) vault with AES-256 encryption. It gives you total ownership of your 2FA tokens without relying on third-party cloud vaults.',
                'sort_order' => 1,
                'is_published' => true,
            ],
            [
                'question' => 'How do I install Vaulta on my mobile device (PWA)?',
                'answer' => 'On iOS Safari, tap the Share button and choose "Add to Home Screen". On Android Chrome, tap "Install app" on the prompt or in your browser menu. Vaulta functions like a native application with full offline capabilities.',
                'sort_order' => 2,
                'is_published' => true,
            ],
            [
                'question' => 'Can I use Vaulta offline?',
                'answer' => 'Yes! The Vaulta Progressive Web App (PWA) caches the application shell, styling, and offline assets. TOTP codes adhere to RFC 6238 and are generated locally using your device clock.',
                'sort_order' => 3,
                'is_published' => true,
            ],
            [
                'question' => 'How secure are my TOTP secrets?',
                'answer' => 'All secrets are encrypted with AES-256 at rest. Vaulta incorporates WebAuthn passkey authentication, rate limiting, Content Security Policy (CSP), and zero third-party telemetry.',
                'sort_order' => 4,
                'is_published' => true,
            ],
            [
                'question' => 'How can I import or export my accounts?',
                'answer' => 'You can import standard otpauth:// URIs, scan QR codes locally in your browser, or import JSON backups. You can export password-protected AES-256 encrypted backups anytime from your vault.',
                'sort_order' => 5,
                'is_published' => true,
            ],
        ];
    }
}
