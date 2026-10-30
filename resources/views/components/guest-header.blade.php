@php
    use App\Settings\SiteSettings;
    use App\Support\Registration;
    use Illuminate\Support\Facades\Route;

    $registrationOpen = Registration::open() && Route::has('register');
    $settings = app(SiteSettings::class);
    $customLogo = $settings->logoUrl;
    $siteName = $settings->name;
@endphp

<header {{ $attributes->class(['flex items-center justify-between gap-2 sm:gap-4 flex-nowrap']) }}>
    <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight shrink-0" wire:navigate.hover>
        @if ($customLogo)
            <span class="flex size-8 items-center justify-center rounded-lg bg-accent/10 p-1 shrink-0 overflow-hidden shadow-2xs">
                <img src="{{ $customLogo }}" alt="{{ $siteName }}" class="size-full object-contain" />
            </span>
        @else
            <span class="flex size-8 items-center justify-center rounded-lg bg-accent text-accent-foreground shrink-0 shadow-2xs">
                <x-app-logo-icon class="size-4.5 fill-current" />
            </span>
        @endif
        <span class="hidden sm:inline text-base sm:text-lg font-bold">{{ $siteName }}</span>
    </a>

    <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0 flex-nowrap">
        <x-ui.theme-toggle />

        <nav class="flex items-center gap-1 sm:gap-2 shrink-0 flex-nowrap">
            @auth
                <flux:button variant="primary" size="sm" :href="route('vault')" wire:navigate.hover class="shrink-0 whitespace-nowrap !h-7 sm:!h-8 !px-2.5 sm:!px-3 !text-xs sm:!text-sm">
                    {{ __('Open vault') }}
                </flux:button>
            @else
                <flux:button variant="ghost" size="sm" :href="route('login')" wire:navigate.hover class="shrink-0 whitespace-nowrap !h-7 sm:!h-8 !px-2 sm:!px-3 !text-xs sm:!text-sm">
                    {{ __('Sign in') }}
                </flux:button>
                @if ($registrationOpen)
                    <flux:button variant="primary" size="sm" :href="route('register')" wire:navigate.hover class="shrink-0 whitespace-nowrap !h-7 sm:!h-8 !px-2.5 sm:!px-3 !text-xs sm:!text-sm">
                        {{ __('Get started') }}
                    </flux:button>
                @endif
            @endauth
        </nav>
    </div>
</header>
