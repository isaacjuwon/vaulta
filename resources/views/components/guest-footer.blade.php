@php
    use App\Settings\SiteSettings;

    $settings = app(SiteSettings::class);
    $copyright = $settings->copyright;
    $tagline = $settings->tagline;
    $socialLinks = $settings->socialLinks;
@endphp

<footer {{ $attributes->class(['flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-zinc-200/80 pt-6 text-xs opacity-75 dark:border-zinc-800']) }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
        <span class="font-medium">{{ $copyright }}</span>
        <flux:link :href="route('about')" wire:navigate.hover>{{ __('About') }}</flux:link>
        <flux:link :href="route('faq')" wire:navigate.hover>{{ __('FAQ') }}</flux:link>
        <flux:link :href="route('privacy')" wire:navigate.hover>{{ __('Privacy') }}</flux:link>
        <flux:link :href="route('terms')" wire:navigate.hover>{{ __('Terms') }}</flux:link>
        <flux:link :href="route('contact')" wire:navigate.hover>{{ __('Contact') }}</flux:link>
    </div>

    <div class="flex items-center gap-4">
        @if (! empty($socialLinks))
            <div class="flex items-center gap-2.5">
                @if (isset($socialLinks['github']))
                    <a href="{{ $socialLinks['github'] }}" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub" class="hover:text-accent transition opacity-80 hover:opacity-100">
                        <x-icons.brands.github class="size-3.5 fill-current" />
                    </a>
                @endif
                @if (isset($socialLinks['twitter']))
                    <a href="{{ $socialLinks['twitter'] }}" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)" class="hover:text-accent transition opacity-80 hover:opacity-100">
                        <x-icons.brands.x class="size-3 fill-current" />
                    </a>
                @endif
                @if (isset($socialLinks['discord']))
                    <a href="{{ $socialLinks['discord'] }}" target="_blank" rel="noopener noreferrer" aria-label="Discord" title="Discord" class="hover:text-accent transition opacity-80 hover:opacity-100">
                        <x-icons.brands.discord class="size-3.5 fill-current" />
                    </a>
                @endif
                @if (isset($socialLinks['bluesky']))
                    <a href="{{ $socialLinks['bluesky'] }}" target="_blank" rel="noopener noreferrer" aria-label="Bluesky" title="Bluesky" class="hover:text-accent transition opacity-80 hover:opacity-100">
                        <x-icons.brands.bluesky class="size-3.5 fill-current" />
                    </a>
                @endif
            </div>
        @endif

        <div class="text-[11px] opacity-70">
            {{ $tagline }}
        </div>
    </div>
</footer>
