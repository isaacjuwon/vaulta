<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Faq>
 */
final class FaqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'question' => fake()->sentence(nbWords: 6).'?',
            'answer' => fake()->paragraph(nbSentences: 3),
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): self
    {
        return $this->state(state: fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }
}
