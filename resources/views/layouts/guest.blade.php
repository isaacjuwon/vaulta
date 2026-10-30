@props([
    'title' => null,
])
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
    class="overflow-x-hidden max-w-full"
>
    <head>
        @include('partials.head')
    </head>
    <body class="cozy-bg min-h-screen antialiased overflow-x-hidden max-w-full w-full">
        {{ $slot }}

        @persist('pwa-prompt')
            <x-ui.pwa-prompt />
        @endpersist

        @fluxScripts(['nonce' => Vite::cspNonce()])
    </body>
</html>
