<?php

declare(strict_types=1);

use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Frequently Asked Questions — Vaulta')] class extends Component {
    #[Url(as: 'q', history: false)]
    public string $search = '';

    /**
     * @return Collection<int, Faq>
     */
    #[Computed]
    public function faqs(): Collection
    {
        $query = Faq::query()->published()->ordered();

        if (filled($this->search)) {
            $term = '%'.trim(string: $this->search).'%';
            $query->where(column: static function ($q) use ($term): void {
                $q->where(column: 'question', operator: 'like', value: $term)
                    ->orWhere(column: 'answer', operator: 'like', value: $term);
            });
        }

        return $query->get();
    }
};
?>

<div class="mx-auto flex min-h-svh max-w-4xl flex-col justify-between px-4 py-6 sm:px-10 sm:py-8">
    <x-guest-header class="border-b border-zinc-200/80 pb-6 dark:border-zinc-800" />

    <main class="my-10 space-y-8">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <div class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50/80 px-3 py-1 text-xs font-medium dark:border-zinc-700 dark:bg-zinc-900/80">
                <flux:icon name="question-mark-circle" class="size-3.5 text-accent" />
                <span>{{ __('Help Center & Documentation') }}</span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">
                {{ __('Frequently Asked Questions') }}
            </h1>

            <p class="text-xs sm:text-sm opacity-75 leading-relaxed">
                {{ __('Find quick answers regarding TOTP encryption, browser-based QR scanning, offline PWA features, and self-hosted deployments.') }}
            </p>
        </div>

        <div class="max-w-xl mx-auto">
            <flux:input
                wire:model.live.debounce.150ms="search"
                :placeholder="__('Search questions, answers, or keywords...')"
                icon="magnifying-glass"
                clearable
            />
        </div>

        <div class="space-y-4">
            @if ($this->faqs->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-xs opacity-75 dark:border-zinc-700">
                    {{ __('No matching questions found for ":search".', ['search' => $this->search]) }}
                </div>
            @else
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 shadow-xs dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/80">
                    @foreach ($this->faqs as $index => $faq)
                        <div
                            wire:key="faq-{{ $faq->id }}"
                            x-data="{ open: {{ $index < 2 ? 'true' : 'false' }} }"
                            class="p-5 sm:p-6 transition hover:bg-zinc-100/50 dark:hover:bg-zinc-800/40"
                        >
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="flex w-full items-center justify-between gap-4 text-start font-semibold text-sm sm:text-base cursor-pointer select-none"
                            >
                                <span class="flex items-center gap-3">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-accent/15 text-accent text-xs font-mono font-bold">
                                        {{ $index + 1 }}
                                    </span>
                                    <span>{{ $faq->question }}</span>
                                </span>

                                <flux:icon
                                    name="chevron-down"
                                    class="size-4 shrink-0 transition-transform duration-200 opacity-60"
                                    x-bind:class="{ 'rotate-180': open }"
                                />
                            </button>

                            <div
                                x-show="open"
                                x-collapse
                                class="mt-3.5 pl-9 text-xs sm:text-sm leading-relaxed opacity-80 whitespace-pre-line"
                            >
                                {{ $faq->answer }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-100/70 p-6 text-center sm:p-8 space-y-3 dark:border-zinc-700 dark:bg-zinc-900/60">
            <h3 class="font-bold text-base">{{ __('Have a different question?') }}</h3>
            <p class="text-xs opacity-75 max-w-md mx-auto">
                {{ __('If you need further assistance or want to report an issue, send a message directly to instance administrators.') }}
            </p>
            <div>
                <flux:button variant="primary" size="sm" :href="route('contact')" wire:navigate.hover icon="envelope">
                    {{ __('Contact support') }}
                </flux:button>
            </div>
        </div>
    </main>

    <x-guest-footer />
</div>
