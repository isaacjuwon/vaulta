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
        <header class="absolute top-4 end-4 z-20 sm:top-6 sm:end-6">
            <x-ui.theme-toggle />
        </header>

        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate.hover>
                    <span class="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                        <x-app-logo-icon class="size-5 fill-current" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Vaulta') }}</span>
                </a>

                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/85 p-6 shadow-sm sm:p-8 dark:border-zinc-700 dark:bg-zinc-900/85">
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
