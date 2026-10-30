@props([
    'align' => 'end',
])
@php
    use App\Enums\Palette;
    use Illuminate\Support\Js;

    $palettes = Palette::cases();
    $darkPalettes = array_values(array_filter($palettes, static fn (Palette $p): bool => $p->isDark()));
    $lightPalettes = array_values(array_filter($palettes, static fn (Palette $p): bool => ! $p->isDark()));

    $darkMap = [];
    $labelMap = [];
    foreach ($palettes as $case) {
        $darkMap[$case->value] = $case->isDark();
        $labelMap[$case->value] = $case->label();
    }
@endphp

<div
    {{ $attributes->class(['relative inline-block text-start']) }}
    x-data="{
        open: false,
        activePalette: document.documentElement.dataset.palette || localStorage.getItem('vaulta.palette') || {{ Js::from(Palette::default()->value) }},
        darkPalettes: {{ Js::from($darkMap) }},
        labels: {{ Js::from($labelMap) }},
        get activeLabel() {
            return this.labels[this.activePalette] || 'Theme';
        },
        init() {
            this.activePalette = document.documentElement.dataset.palette || localStorage.getItem('vaulta.palette') || {{ Js::from(Palette::default()->value) }};
            window.addEventListener('vaulta-palette-changed', (e) => {
                if (e.detail?.palette && e.detail.palette !== this.activePalette) {
                    this.activePalette = e.detail.palette;
                }
            });
        },
        selectTheme(slug) {
            if (this.activePalette === slug) {
                this.open = false;
                return;
            }
            const isDark = Boolean(this.darkPalettes[slug]);
            const apply = () => {
                this.activePalette = slug;
                if (typeof window.__applyVaultaPalette === 'function') {
                    window.__applyVaultaPalette(slug);
                } else {
                    document.documentElement.dataset.palette = slug;
                    document.documentElement.classList.toggle('dark', isDark);
                    try {
                        localStorage.setItem('vaulta.palette', slug);
                        document.cookie = 'vaulta_palette=' + slug + '; path=/; max-age=31536000; SameSite=Lax';
                    } catch (e) {}
                }
                window.dispatchEvent(new CustomEvent('vaulta-palette-changed', { detail: { palette: slug } }));
            };

            if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.startViewTransition(apply);
            } else {
                apply();
            }

            @auth
                fetch('{{ route('settings.palette.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ palette: slug })
                }).catch(() => {});
            @endauth

            this.open = false;
        }
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
>
    {{-- Trigger Button --}}
    <button
        type="button"
        x-on:click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="true"
        aria-label="{{ __('Select theme') }}"
        title="{{ __('Switch theme') }}"
        class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg border border-zinc-200 bg-zinc-50/90 px-2 sm:px-2.5 h-7 sm:h-8 text-xs font-medium text-zinc-700 shadow-2xs transition hover:border-accent hover:bg-zinc-100 hover:text-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-300 dark:hover:bg-zinc-800"
    >
        <span class="flex size-3.5 items-center justify-center rounded-full border border-black/15 bg-accent dark:border-white/20" aria-hidden="true"></span>
        <span class="hidden sm:inline truncate max-w-[90px]" x-text="activeLabel"></span>
        <flux:icon name="chevron-down" class="size-3 opacity-60 transition group-hover:opacity-100" />
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        @class([
            'absolute mt-2 z-50 w-72 sm:w-80 rounded-xl border border-zinc-200 bg-white/95 p-3 shadow-xl backdrop-blur-md dark:border-zinc-700 dark:bg-zinc-900/95',
            'end-0' => $align === 'end',
            'start-0' => $align === 'start',
        ])
        role="menu"
        aria-orientation="vertical"
    >
        <div class="mb-2.5 flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-800">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ __('Themes (15)') }}
            </span>
            <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                {{ __('Self-hosted') }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
            {{-- Dark Themes Column --}}
            <div>
                <div class="px-1.5 pb-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                    {{ __('Dark') }}
                </div>
                <div class="space-y-0.5">
                    @foreach ($darkPalettes as $theme)
                        @php $colors = $theme->previewColors(); @endphp
                        <button
                            type="button"
                            wire:key="theme-toggle-{{ $theme->value }}"
                            x-on:click="selectTheme('{{ $theme->value }}')"
                            :class="activePalette === '{{ $theme->value }}' ? 'bg-accent/15 text-accent font-semibold border-accent/40' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 border-transparent'"
                            class="flex w-full items-center justify-between gap-1.5 rounded-lg border px-2 py-1.5 text-start transition cursor-pointer"
                            role="menuitem"
                        >
                            <span class="flex items-center gap-1.5 truncate">
                                <span
                                    class="relative inline-flex size-3.5 shrink-0 items-center justify-center rounded-full border border-black/20 overflow-hidden dark:border-white/20"
                                    style="background-color: {{ $colors['bg'] }};"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        style="background-color: {{ $colors['accent'] }};"
                                    ></span>
                                </span>
                                <span class="truncate">{{ $theme->label() }}</span>
                            </span>
                            <span x-show="activePalette === '{{ $theme->value }}'" x-cloak>
                                <flux:icon name="check" class="size-3 text-accent" />
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Light Themes Column --}}
            <div>
                <div class="px-1.5 pb-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                    {{ __('Light') }}
                </div>
                <div class="space-y-0.5">
                    @foreach ($lightPalettes as $theme)
                        @php $colors = $theme->previewColors(); @endphp
                        <button
                            type="button"
                            wire:key="theme-toggle-{{ $theme->value }}"
                            x-on:click="selectTheme('{{ $theme->value }}')"
                            :class="activePalette === '{{ $theme->value }}' ? 'bg-accent/15 text-accent font-semibold border-accent/40' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 border-transparent'"
                            class="flex w-full items-center justify-between gap-1.5 rounded-lg border px-2 py-1.5 text-start transition cursor-pointer"
                            role="menuitem"
                        >
                            <span class="flex items-center gap-1.5 truncate">
                                <span
                                    class="relative inline-flex size-3.5 shrink-0 items-center justify-center rounded-full border border-black/20 overflow-hidden dark:border-white/20"
                                    style="background-color: {{ $colors['bg'] }};"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        style="background-color: {{ $colors['accent'] }};"
                                    ></span>
                                </span>
                                <span class="truncate">{{ $theme->label() }}</span>
                            </span>
                            <span x-show="activePalette === '{{ $theme->value }}'" x-cloak>
                                <flux:icon name="check" class="size-3 text-accent" />
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
