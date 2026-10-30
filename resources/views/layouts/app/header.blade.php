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
        <flux:header container class="border-b border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
            <x-app-logo href="{{ route('vault') }}" wire:navigate.hover />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="key" :href="route('vault')" :current="request()->routeIs('vault')" wire:navigate.hover>
                    {{ __('Vault') }}
                </flux:navbar.item>
                <flux:navbar.item icon="paint-brush" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate.hover>
                    {{ __('Appearance') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />
            
            <div class="mr-5">
		<x-ui.theme-toggle />
	    </div>
            <x-desktop-user-menu />
        </flux:header>

        {{ $slot }}

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
