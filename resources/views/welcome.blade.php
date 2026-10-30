@php
    use App\Support\Registration;

    $registrationOpen = Registration::open();
@endphp

<x-layouts::guest :title="__('Self-hosted two-factor authenticator vault')">
    <div class="mx-auto flex min-h-svh w-full max-w-6xl min-w-0 flex-col justify-between px-4 py-6 sm:px-10 sm:py-8 overflow-x-hidden">
        <x-guest-header />

        <main class="my-8 sm:my-10 grid w-full max-w-full min-w-0 items-center gap-8 sm:gap-10 lg:grid-cols-12">
            <div class="w-full max-w-full min-w-0 space-y-5 sm:space-y-6 lg:col-span-7">
                <div class="inline-flex max-w-full min-w-0 items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50/80 px-2.5 py-1 text-[11px] sm:px-3 sm:text-xs font-medium dark:border-zinc-700 dark:bg-zinc-900/80">
                    <flux:icon name="shield-check" class="size-3.5 text-accent shrink-0" />
                    <span class="truncate min-w-0">
                        <span class="sm:hidden">{{ __('RFC 6238 TOTP · AES-256 encrypted') }}</span>
                        <span class="hidden sm:inline">{{ __('RFC 6238 TOTP · AES-256 encrypted secrets · 15 self-hosted themes') }}</span>
                    </span>
                </div>

                <h1 class="text-2xl font-bold tracking-tight sm:text-4xl lg:text-5xl break-words [overflow-wrap:anywhere]">
                    {{ __('Your two-factor codes, stored under your own roof.') }}
                </h1>

                <p class="max-w-xl text-xs sm:text-base leading-relaxed opacity-80 break-words [overflow-wrap:anywhere]">
                    {{ __('Vaulta is a fast, private, self-hosted authenticator built with Laravel 13 and Livewire 4. Import otpauth:// URIs or scan QR codes locally in your browser, lock down access with passkeys, and dress the vault in 15 tactile retro and modern palettes.') }}
                </p>

                <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 w-full max-w-full">
                    @auth
                        <flux:button variant="primary" icon="key" :href="route('vault')" wire:navigate.hover class="w-full sm:w-auto max-sm:!text-xs max-sm:!h-9 max-sm:!px-3">
                            {{ __('Go to your vault') }}
                        </flux:button>
                    @else
                        @if ($registrationOpen)
                            <flux:button variant="primary" icon="plus" :href="route('register')" wire:navigate.hover class="w-full sm:w-auto max-sm:!text-xs max-sm:!h-9 max-sm:!px-3">
                                {{ __('Get started') }}
                            </flux:button>
                        @endif
                        <flux:button variant="outline" :href="route('login')" wire:navigate.hover class="w-full sm:w-auto max-sm:!text-xs max-sm:!h-9 max-sm:!px-3">
                            <span class="sm:hidden">{{ __('Sign in') }}</span>
                            <span class="hidden sm:inline">{{ __('Sign in to existing vault') }}</span>
                        </flux:button>
                    @endauth
                </div>
            </div>

            <div class="w-full max-w-full min-w-0 lg:col-span-5">
                <div class="w-full max-w-full min-w-0 space-y-2.5 sm:space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/85 p-3 sm:p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/85 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-2.5 sm:pb-3 dark:border-zinc-700 min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="lock-closed" class="size-4 text-accent shrink-0" />
                            <span class="truncate text-xs sm:text-sm font-semibold">{{ __('Vault preview') }}</span>
                        </div>
                        <span class="font-mono text-[11px] sm:text-xs opacity-70 shrink-0">SHA-1 · 30s</span>
                    </div>

                    @foreach ([
                        ['issuer' => 'GitHub', 'account' => 'ops@vaulta.local', 'code' => '482 910', 'dot' => 'bg-emerald-500'],
                        ['issuer' => 'Cloudflare', 'account' => 'infra@vaulta.local', 'code' => '193 604', 'dot' => 'bg-amber-500'],
                        ['issuer' => 'Fastmail', 'account' => 'security@vaulta.local', 'code' => '740 218', 'dot' => 'bg-sky-500'],
                    ] as $sample)
                        <div class="flex items-center justify-between gap-2 rounded-xl border border-zinc-200 bg-white/70 p-2.5 sm:gap-4 sm:px-4 sm:py-3 dark:border-zinc-700 dark:bg-zinc-950/60 min-w-0 w-full">
                            <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                                <x-ui.issuer-icon :issuer="$sample['issuer']" size="sm" class="shrink-0 max-sm:size-7" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="size-1.5 sm:size-2 rounded-full {{ $sample['dot'] }} shrink-0"></span>
                                        <span class="truncate text-xs sm:text-sm font-semibold">{{ $sample['issuer'] }}</span>
                                    </div>
                                    <div class="truncate font-mono text-[10px] sm:text-xs opacity-70">{{ $sample['account'] }}</div>
                                </div>
                            </div>
                            <div class="font-mono text-xs sm:text-base md:text-xl font-bold tracking-tight text-accent-content shrink-0 tabular-nums whitespace-nowrap pl-1 sm:pl-2">
                                {{ $sample['code'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </main>

        <x-guest-footer />
    </div>
</x-layouts::guest>
