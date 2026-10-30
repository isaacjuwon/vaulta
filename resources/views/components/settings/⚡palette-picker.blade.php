<?php

declare(strict_types=1);

use App\Actions\Appearance\ChangePalette;
use App\Enums\Palette;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public string $palette = 'ferrite';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->palette = $user->resolvedPalette()->value;
    }

    public function selectPalette(string $choice, ChangePalette $changePalette): void
    {
        $this->palette = $choice;

        $validated = $this->validate(rules: [
            'palette' => ['required', 'string', Rule::enum(type: Palette::class)],
        ]);

        $selected = Palette::from(value: $validated['palette']);

        /** @var User $user */
        $user = Auth::user();
        $changePalette->handle(user: $user, palette: $selected);

        $paletteJs = Js::from($selected->value);
        $isDarkJs = Js::from($selected->isDark());

        $this->js(expression: <<<JS
            (() => {
                const applyTheme = () => {
                    if (typeof window.__applyVaultaPalette === 'function') {
                        window.__applyVaultaPalette({$paletteJs});
                    } else {
                        document.documentElement.dataset.palette = {$paletteJs};
                        document.documentElement.classList.toggle('dark', {$isDarkJs});
                        try {
                            localStorage.setItem('vaulta.palette', {$paletteJs});
                            document.cookie = 'vaulta_palette=' + {$paletteJs} + ';path=/;max-age=31536000;SameSite=Lax';
                        } catch (e) {}
                    }
                    window.dispatchEvent(new CustomEvent('vaulta-palette-changed', { detail: { palette: {$paletteJs} } }));
                };
                const allowMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: no-preference)').matches;
                if (allowMotion && typeof document.startViewTransition === 'function') {
                    document.startViewTransition(applyTheme);
                } else {
                    applyTheme();
                }
            })();
        JS);

        Flux::toast(variant: 'success', text: __('Theme switched to :label.', ['label' => $selected->label()]));
    }
};
?>

<div class="grid gap-4">
    <div
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
        role="radiogroup"
        aria-label="{{ __('Theme palette') }}"
    >
        @foreach (Palette::cases() as $case)
            @php($isSelected = $palette === $case->value)
            <button
                type="button"
                wire:key="palette-{{ $case->value }}"
                role="radio"
                aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                wire:click="selectPalette('{{ $case->value }}')"
                data-test="palette-card-{{ $case->value }}"
                class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50/70 p-3 text-start transition hover:border-accent focus-visible:outline-2 focus-visible:outline-accent aria-checked:border-accent aria-checked:ring-2 aria-checked:ring-accent/40 dark:border-zinc-700 dark:bg-zinc-900/70"
            >
                {{-- Live CSS preview card rendered with authentic palette tokens --}}
                <div
                    data-palette="{{ $case->value }}"
                    @class([
                        'cozy-bg w-full overflow-hidden rounded-lg border border-zinc-300 p-3 shadow-xs dark:border-zinc-700',
                        'dark' => $case->isDark(),
                    ])
                    aria-hidden="true"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-serif text-xs font-bold tracking-tight">492 817</span>
                        <span
                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                            style="background-color: var(--color-accent); color: var(--color-accent-foreground);"
                        >
                            24s
                        </span>
                    </div>
                    <div class="mt-2.5 totp-bar">
                        <div class="totp-fill" style="width: 72%;"></div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 px-0.5">
                    <flux:icon :name="$case->icon()" class="size-4.5 shrink-0 text-accent" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ $case->label() }}</div>
                        <div class="truncate text-xs opacity-70">{{ $case->subtitle() }}</div>
                    </div>
                </div>
            </button>
        @endforeach
    </div>

    @error('palette')
        <p class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
    @enderror
</div>
