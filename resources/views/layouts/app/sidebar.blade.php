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
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 data-flux-sidebar-on-mobile:bg-zinc-100 dark:data-flux-sidebar-on-mobile:bg-zinc-900 data-flux-sidebar-on-mobile:shadow-2xl">
            <flux:sidebar.header class="flex items-center justify-between">
                <x-app-logo :sidebar="true" href="{{ route('vault') }}" wire:navigate.hover />
                <div class="hidden lg:block">
                    <x-ui.theme-toggle />
                </div>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    <flux:sidebar.item
                        icon="key"
                        :href="route('vault')"
                        :current="request()->routeIs('vault') && request()->query('filter') !== 'Favorites'"
                        wire:navigate.hover
                    >
                        {{ __('Vault') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="star"
                        :href="route('vault', ['filter' => 'Favorites'])"
                        :current="request()->routeIs('vault') && request()->query('filter') === 'Favorites'"
                        wire:navigate.hover
                    >
                        {{ __('Favorites') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Account')" class="grid">
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('settings')" :current="request()->routeIs('settings') || request()->routeIs('settings.*')" wire:navigate.hover>
                        {{ __('Settings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-circle" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate.hover>
                        {{ __('Profile') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shield-check" :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate.hover>
                        {{ __('Password & security') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="computer-desktop" :href="route('sessions.edit')" :current="request()->routeIs('sessions.edit')" wire:navigate.hover>
                        {{ __('Active sessions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="paint-brush" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate.hover>
                        {{ __('Appearance') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()?->is_admin)
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
                        <flux:sidebar.item icon="layout-grid" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>
                            {{ __('Overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate.hover>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="lock-closed" :href="route('admin.authenticators')" :current="request()->routeIs('admin.authenticators')" wire:navigate.hover>
                            {{ __('Authenticators') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="globe-alt" :href="route('admin.sessions')" :current="request()->routeIs('admin.sessions')" wire:navigate.hover>
                            {{ __('Sessions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate.hover>
                            {{ __('Site settings') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden rounded-lg border border-zinc-200 bg-zinc-50/70 px-3 py-2 text-xs lg:flex lg:items-center lg:gap-2 dark:border-zinc-700 dark:bg-zinc-950/60">
                <flux:icon name="lock-closed" class="size-4 shrink-0 text-accent" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold">{{ __('Vault protected') }}</div>
                    <div class="truncate text-[11px] opacity-75">{{ __('AES-256 encrypted storage') }}</div>
                </div>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200 bg-zinc-100 lg:hidden dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('vault') }}" wire:navigate.hover />

            <flux:spacer />

            <x-ui.theme-toggle />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :avatar="auth()->user()->avatar ?: null"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        @if (auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        @else
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />
                        @endif
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate text-xs">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate.hover>
                        {{ __('Settings') }}
                    </flux:menu.item>
                    <flux:menu.item :href="route('appearance.edit')" icon="paint-brush" wire:navigate.hover>
                        {{ __('Appearance') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
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
