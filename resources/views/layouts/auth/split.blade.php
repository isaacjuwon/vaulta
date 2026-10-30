@php
    use App\Enums\Palette;
    use Illuminate\Support\Facades\Vite;

    $activePalette = Palette::current();
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => $activePalette->isDark()])
    data-palette="{{ $activePalette->value }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="cozy-bg min-h-screen antialiased">
        <header class="absolute top-4 end-4 z-30 sm:top-6 sm:end-6">
            <x-ui.theme-toggle />
        </header>

        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col border-e border-zinc-200 bg-zinc-100/85 p-10 lg:flex dark:border-zinc-700 dark:bg-zinc-900/90">
                <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-2.5 text-lg font-semibold" wire:navigate.hover>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                        <x-app-logo-icon class="size-5 fill-current" />
                    </span>
                    {{ config('app.name', 'Vaulta') }}
                </a>

                <div class="relative z-20 mt-auto space-y-2">
                    <flux:heading size="lg">{{ __('Private two-factor codes, stored under your roof.') }}</flux:heading>
                    <flux:text class="text-sm opacity-75">
                        {{ __('RFC 6238 TOTP vault with encrypted secrets, WebAuthn passkeys, and 15 curated themes.') }}
                    </flux:text>
                </div>
            </div>

            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[380px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate.hover>
                        <span class="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                            <x-app-logo-icon class="size-5 fill-current" />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Vaulta') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('pwa-prompt')
            <x-ui.pwa-prompt />
        @endpersist

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts(['nonce' => Vite::cspNonce()])
    </body>
</html>
