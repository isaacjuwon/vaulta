@php
    use App\Enums\Palette;
    use App\Models\SiteSetting;

    $activePalette = Palette::current();
    $message = SiteSetting::get(key: 'maintenance_message') ?? __('Vaulta is currently undergoing scheduled maintenance. Please check back shortly.');
    $retry = (int) (SiteSetting::get(key: 'maintenance_retry') ?? 60);
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => $activePalette->isDark()])
    data-palette="{{ $activePalette->value }}"
>
    <head>
        @include('partials.head', ['title' => __('Under Maintenance')])
        <meta http-equiv="refresh" content="{{ $retry }}">
    </head>
    <body class="cozy-bg min-h-screen antialiased flex flex-col justify-between p-6 sm:p-10">
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-2.5 font-semibold tracking-tight">
                <span class="flex size-9 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                    <x-app-logo-icon class="size-5 fill-current" />
                </span>
                <span class="text-lg">{{ config('app.name', 'Vaulta') }}</span>
            </div>

            <x-ui.theme-toggle />
        </header>

        <main class="my-auto mx-auto max-w-lg text-center space-y-6">
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/10 px-3.5 py-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                <flux:icon name="wrench-screwdriver" class="size-3.5" />
                <span>{{ __('System Maintenance In Progress') }}</span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">
                {{ __('Temporarily Offline for Upgrades') }}
            </h1>

            <p class="text-sm leading-relaxed opacity-80">
                {{ $message }}
            </p>

            <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 font-mono text-xs opacity-75 dark:border-zinc-700 dark:bg-zinc-900/80">
                <span>{{ __('Auto-refreshing in :seconds seconds...', ['seconds' => $retry]) }}</span>
            </div>
        </main>

        <footer class="flex items-center justify-between border-t border-zinc-200/80 pt-6 text-xs opacity-70 dark:border-zinc-800">
            <div>{{ config('app.name', 'Vaulta') }} · {{ __('Self-Hosted Authenticator') }}</div>
            <div>{{ __('HTTP 503 Service Unavailable') }}</div>
        </footer>

        @fluxScripts
    </body>
</html>
