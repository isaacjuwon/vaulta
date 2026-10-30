<?php

declare(strict_types=1);

use App\Enums\OAuthProvider;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $mode = 'login';

    /**
     * @return list<OAuthProvider>
     */
    #[Computed]
    public function providers(): array
    {
        $enabled = OAuthProvider::enabled();

        return ! empty($enabled) ? $enabled : [OAuthProvider::GitHub, OAuthProvider::Google];
    }
};
?>

@if (! empty($this->providers))
    <div class="space-y-4">
        <div class="relative flex items-center justify-center">
            <div class="w-full border-t border-zinc-200 dark:border-zinc-700"></div>
            <span class="absolute bg-zinc-50 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('Or continue with') }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            @foreach ($this->providers as $provider)
                <flux:button
                    :href="route('oauth.redirect', ['provider' => $provider->value])"
                    wire:navigate="false"
                    variant="subtle"
                    class="w-full justify-center gap-2 border border-zinc-200 bg-white shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                    data-test="oauth-login-{{ $provider->value }}"
                >
                    @if ($provider === OAuthProvider::GitHub)
                        <svg class="size-4 shrink-0 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                        </svg>
                    @elseif ($provider === OAuthProvider::Google)
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.01 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                    @endif
                    <span class="text-xs font-semibold">{{ $provider->label() }}</span>
                </flux:button>
            @endforeach
        </div>
    </div>
@endif
