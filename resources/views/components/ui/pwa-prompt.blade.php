<div
    x-data="{
        show: false,
        isIos: false,
        canInstall: false,
        isInstalled: false,
        init() {
            this.isInstalled = Boolean(window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone);
            if (this.isInstalled) return;

            this.isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream && !window.chrome;
            this.checkPrompt();

            window.addEventListener('vaulta-pwa-ready', () => {
                this.checkPrompt();
            });

            window.addEventListener('vaulta-pwa-installed', () => {
                this.isInstalled = true;
                this.show = false;
            });

            window.addEventListener('vaulta-show-pwa-prompt', () => {
                this.show = true;
            });
        },
        checkPrompt() {
            if (this.isInstalled) return;

            const dismissedAt = localStorage.getItem('vaulta.pwa-dismissed');
            // If dismissed less than 2 days ago, do not auto-show
            if (dismissedAt && (Date.now() - Number(dismissedAt) < 172800000)) {
                return;
            }

            this.canInstall = Boolean(window.__vaultaPwaPrompt);

            // Delay showing by 1.2s so initial page animation finishes smoothly
            if (this.canInstall || this.isIos) {
                setTimeout(() => {
                    if (!this.isInstalled) {
                        this.show = true;
                    }
                }, 1200);
            }
        },
        async install() {
            if (window.__vaultaPwaPrompt) {
                try {
                    await window.__vaultaPwaPrompt.prompt();
                    const choice = await window.__vaultaPwaPrompt.userChoice;
                    if (choice?.outcome === 'accepted') {
                        this.show = false;
                    }
                } catch (e) {
                    this.show = false;
                }
            }
        },
        dismiss() {
            this.show = false;
            try {
                localStorage.setItem('vaulta.pwa-dismissed', Date.now().toString());
            } catch (e) {}
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-4 scale-95"
    x-cloak
    class="fixed bottom-4 inset-x-3 sm:inset-x-auto sm:right-6 sm:max-w-md z-50 rounded-2xl border border-zinc-200 bg-white/95 p-4 shadow-2xl backdrop-blur-md dark:border-zinc-700 dark:bg-zinc-900/95"
    role="dialog"
    aria-labelledby="pwa-prompt-title"
>
    <div class="flex items-start gap-3.5">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground shadow-xs">
            <x-app-logo-icon class="size-5 fill-current" />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
                <h3 id="pwa-prompt-title" class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Install Vaulta') }}
                </h3>
                <button
                    type="button"
                    x-on:click="dismiss()"
                    class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 transition"
                    aria-label="{{ __('Dismiss install prompt') }}"
                >
                    <flux:icon name="x-mark" class="size-4" />
                </button>
            </div>

            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                <template x-if="canInstall">
                    <span>{{ __('Install Vaulta on your home screen for rapid offline 2FA codes and instant launch.') }}</span>
                </template>
                <template x-if="isIos && !canInstall">
                    <span>{{ __('Tap the Share button below, then select "Add to Home Screen" to install Vaulta.') }}</span>
                </template>
            </p>

            <div class="mt-3 flex items-center gap-2">
                <template x-if="canInstall">
                    <flux:button variant="primary" size="xs" x-on:click="install()" class="font-semibold">
                        <flux:icon name="arrow-down-tray" class="size-3.5 mr-1" />
                        {{ __('Install app') }}
                    </flux:button>
                </template>

                <template x-if="isIos && !canInstall">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-accent">
                        <flux:icon name="share" class="size-3.5" />
                        <span>{{ __('Share → Add to Home Screen') }}</span>
                    </span>
                </template>

                <flux:button variant="ghost" size="xs" x-on:click="dismiss()" class="text-xs">
                    {{ __('Not now') }}
                </flux:button>
            </div>
        </div>
    </div>
</div>
