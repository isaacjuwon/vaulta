<div class="flex items-start max-md:flex-col">
    {{-- Desktop sidebar navigation --}}
    <div class="me-10 hidden w-[220px] pb-4 md:block">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit') || (request()->routeIs('settings') && request()->query('tab', 'profile') === 'profile')" wire:navigate.hover>
                {{ __('Profile') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" :current="request()->routeIs('security.edit') || (request()->routeIs('settings') && request()->query('tab') === 'security')" wire:navigate.hover>
                {{ __('Password & security') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('sessions.edit')" :current="request()->routeIs('sessions.edit') || (request()->routeIs('settings') && request()->query('tab') === 'sessions')" wire:navigate.hover>
                {{ __('Active sessions') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit') || (request()->routeIs('settings') && request()->query('tab') === 'appearance')" wire:navigate.hover>
                {{ __('Appearance') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('settings', ['tab' => 'oauth'])" :current="request()->routeIs('settings') && request()->query('tab') === 'oauth'" wire:navigate.hover>
                {{ __('Connected accounts') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    {{-- Mobile fluid horizontal scrolling tabs --}}
    <div class="w-full pb-3 md:hidden">
        <div
            class="fluid-tabs -mx-4 px-4 pb-1 border-b border-zinc-200 dark:border-zinc-800"
            x-data="{
                scrollToActive(behavior = 'smooth') {
                    const active = this.$el.querySelector('[data-current=\'true\'], [data-current=\'1\'], [aria-current=\'page\']');
                    if (active) {
                        active.scrollIntoView({ behavior: behavior, block: 'nearest', inline: 'center' });
                    }
                }
            }"
            x-init="
                $nextTick(() => scrollToActive('auto'));
                setTimeout(() => scrollToActive('smooth'), 120);
            "
            x-on:livewire:navigated.window="$nextTick(() => scrollToActive('smooth'))"
        >
            <flux:navbar class="-mb-px flex-nowrap shrink-0 gap-1">
                <flux:navbar.item icon="user-circle" :href="route('profile.edit')" :current="request()->routeIs('profile.edit') || (request()->routeIs('settings') && request()->query('tab', 'profile') === 'profile')" wire:navigate.hover class="shrink-0 whitespace-nowrap">
                    {{ __('Profile') }}
                </flux:navbar.item>
                <flux:navbar.item icon="shield-check" :href="route('security.edit')" :current="request()->routeIs('security.edit') || (request()->routeIs('settings') && request()->query('tab') === 'security')" wire:navigate.hover class="shrink-0 whitespace-nowrap">
                    {{ __('Password & security') }}
                </flux:navbar.item>
                <flux:navbar.item icon="computer-desktop" :href="route('sessions.edit')" :current="request()->routeIs('sessions.edit') || (request()->routeIs('settings') && request()->query('tab') === 'sessions')" wire:navigate.hover class="shrink-0 whitespace-nowrap">
                    {{ __('Active sessions') }}
                </flux:navbar.item>
                <flux:navbar.item icon="paint-brush" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit') || (request()->routeIs('settings') && request()->query('tab') === 'appearance')" wire:navigate.hover class="shrink-0 whitespace-nowrap">
                    {{ __('Appearance') }}
                </flux:navbar.item>
                <flux:navbar.item icon="link" :href="route('settings', ['tab' => 'oauth'])" :current="request()->routeIs('settings') && request()->query('tab') === 'oauth'" wire:navigate.hover class="shrink-0 whitespace-nowrap">
                    {{ __('Connected accounts') }}
                </flux:navbar.item>
            </flux:navbar>
        </div>
    </div>

    <div class="flex-1 self-stretch max-md:pt-4" style="view-transition-name: settings-pane;">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</div>
