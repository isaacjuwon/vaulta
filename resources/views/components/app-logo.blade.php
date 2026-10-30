@php
    use App\Settings\SiteSettings;

    $settings = app(SiteSettings::class);
    $customLogo = $settings->logoUrl;
    $siteName = $settings->name;
@endphp

@props([
    'sidebar' => false,
])

<a {{ $attributes->class('inline-flex items-center gap-2.5 font-semibold tracking-tight') }}>
    @if ($customLogo)
        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-accent/10 p-1 overflow-hidden shadow-xs">
            <img src="{{ $customLogo }}" alt="{{ $siteName }}" class="size-full object-contain" />
        </span>
    @else
        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-accent text-accent-foreground shadow-xs">
            <x-app-logo-icon class="size-4.5" />
        </span>
    @endif
    <span data-wordmark class="font-serif text-base font-bold tracking-tight">
        {{ $siteName }}
    </span>
</a>
