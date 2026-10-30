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
            <div class="flex w-full max-w-sm flex-col gap-6 rounded-2xl border border-zinc-200 bg-zinc-50/90 p-7 shadow-md dark:border-zinc-700 dark:bg-zinc-900/90">
                <div class="flex justify-center">
                    <x-app-logo href="{{ route('home') }}" wire:navigate.hover />
                </div>

                <div class="flex flex-col gap-6">
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
