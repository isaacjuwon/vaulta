<?php

declare(strict_types=1);

use App\Support\IssuerBrand;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

new class extends Component {
    #[Modelable]
    public string $value = '';

    public bool $showModal = false;

    public string $search = '';

    public function openModal(): void
    {
        $this->search = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function selectIcon(string $provider): void
    {
        $this->value = $provider;
        $this->dispatch(event: 'icon-selected', provider: $provider);
        $this->showModal = false;
    }

    /**
     * @return list<array{name: string, brand: string}>
     */
    #[Computed]
    public function filteredPresets(): array
    {
        $presets = IssuerBrand::presets();
        $term = mb_strtolower(string: trim(string: $this->search));

        if ($term === '') {
            return $presets;
        }

        return array_values(array: array_filter(
            array: $presets,
            callback: static fn (array $preset): bool => str_contains(
                haystack: mb_strtolower(string: $preset['name']),
                needle: $term,
            ) || str_contains(
                haystack: mb_strtolower(string: $preset['brand']),
                needle: $term,
            ),
        ));
    }
};
?>

<div>
    {{-- Trigger Card --}}
    <div
        role="button"
        tabindex="0"
        wire:click="openModal"
        wire:keydown.enter="openModal"
        wire:keydown.space.prevent="openModal"
        data-test="open-icon-picker-modal"
        class="group flex cursor-pointer items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 transition hover:border-accent/60 hover:bg-zinc-100/80 dark:border-zinc-700 dark:bg-zinc-900/80 dark:hover:bg-zinc-800/80"
    >
        <div class="flex items-center gap-3 min-w-0">
            <x-ui.issuer-icon :issuer="$value" size="sm" class="!size-9 shadow-xs" />
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="truncate text-xs font-semibold">
                        {{ $value !== '' ? $value : __('No icon selected') }}
                    </p>
                    @if ($value !== '' && IssuerBrand::detect(issuer: $value) !== null)
                        <span class="inline-flex items-center rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                            {{ __('Matched') }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 truncate text-[11px] opacity-75">
                    @if ($value !== '' && IssuerBrand::detect(issuer: $value) !== null)
                        {{ __('Official brand icon active') }}
                    @elseif ($value !== '')
                        {{ __('Custom initials monogram') }}
                    @else
                        {{ __('Click to pick a provider icon') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-1.5 text-xs font-medium text-accent">
            <span>{{ __('Choose') }}</span>
            <flux:icon name="chevron-right" class="size-3.5 transition group-hover:translate-x-0.5" />
        </div>
    </div>

    {{-- Icon Picker Modal --}}
    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Select account icon') }}</flux:heading>
                <flux:subheading>{{ __('Choose a popular provider to autofill the service name and icon.') }}</flux:subheading>
            </div>

            <div>
                <flux:input
                    wire:model.live.debounce.150ms="search"
                    placeholder="Search providers (e.g. GitHub, Google, AWS)..."
                    icon="magnifying-glass"
                    clearable
                    autofocus
                    data-test="icon-search-input"
                />
            </div>

            <div class="max-h-[300px] overflow-y-auto pr-1">
                @if (empty($this->filteredPresets))
                    <div class="py-8 text-center">
                        <flux:text>{{ __('No providers found matching ":term"', ['term' => $search]) }}</flux:text>
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4" role="radiogroup" aria-label="{{ __('Available icons') }}">
                        @foreach ($this->filteredPresets as $preset)
                            @php
                                $isSelected = strcasecmp($value, $preset['name']) === 0
                                    || IssuerBrand::detect(issuer: $value) === $preset['brand'];
                            @endphp
                            <button
                                type="button"
                                wire:key="picker-preset-{{ $preset['brand'] }}"
                                role="radio"
                                aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                wire:click="selectIcon('{{ $preset['name'] }}')"
                                data-test="select-icon-{{ $preset['brand'] }}"
                                title="{{ $preset['name'] }}"
                                class="group flex flex-col items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 text-xs font-medium transition hover:border-accent hover:bg-zinc-100 aria-checked:border-accent aria-checked:bg-accent/10 aria-checked:ring-2 aria-checked:ring-accent/40 dark:border-zinc-700 dark:bg-zinc-900/80 dark:hover:bg-zinc-800"
                            >
                                <x-dynamic-component
                                    :component="'icons.brands.' . $preset['brand']"
                                    class="size-6 fill-current text-zinc-700 transition group-hover:text-accent group-aria-checked:text-accent dark:text-zinc-300"
                                />
                                <span class="truncate text-xs">{{ $preset['name'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="closeModal">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
