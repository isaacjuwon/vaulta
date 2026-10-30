<?php

declare(strict_types=1);

use App\Actions\Contact\SubmitContactMessage;
use App\Models\SiteSetting;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Contact & Support — Vaulta')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    public bool $sent = false;

    public string $supportEmail = '';

    public function mount(): void
    {
        $this->supportEmail = (string) (SiteSetting::get(key: 'support_email') ?? 'admin@vaulta.local');

        /** @var User|null $user */
        $user = Auth::user();
        if ($user !== null) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function submit(SubmitContactMessage $submitContactMessage): void
    {
        $ip = request()->ip() ?? '127.0.0.1';
        $throttleKey = 'contact-submit:'.$ip;

        if (RateLimiter::tooManyAttempts(key: $throttleKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn(key: $throttleKey);
            $this->addError(name: 'message', message: __('Too many contact attempts. Please wait :seconds seconds before trying again.', ['seconds' => $seconds]));

            return;
        }

        RateLimiter::hit(key: $throttleKey, decaySeconds: 3600);

        /** @var User|null $user */
        $user = Auth::user();

        $submitContactMessage->handle(
            data: [
                'name' => $this->name,
                'email' => $this->email,
                'subject' => $this->subject,
                'message' => $this->message,
            ],
            user: $user,
            ipAddress: $ip,
        );

        $this->sent = true;
        $this->reset(['subject', 'message']);
        Flux::toast(variant: 'success', text: __('Your message has been sent to the instance administrator.'));
    }
};
?>

<div class="mx-auto flex min-h-svh max-w-5xl flex-col justify-between px-4 py-6 sm:px-10 sm:py-8">
    <x-guest-header class="border-b border-zinc-200/80 pb-6 dark:border-zinc-800" />

    <main class="my-10 grid items-start gap-10 lg:grid-cols-12">
        {{-- Left: Instance Info & Diagnostic Card --}}
        <div class="space-y-6 lg:col-span-5">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">{{ __('Contact & support') }}</flux:heading>
                <flux:subheading>
                    {{ __('Have questions, security disclosures, or feedback about this Vaulta installation?') }}
                </flux:subheading>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-5 space-y-4 text-xs dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="flex items-center gap-2 font-semibold">
                    <flux:icon name="envelope" class="size-4 text-accent" />
                    <span>{{ __('Instance administrator') }}</span>
                </div>
                <div class="font-mono text-xs opacity-85 select-all">
                    {{ $supportEmail }}
                </div>
                <p class="text-[11px] opacity-70">
                    {{ __('You may reach out directly via email or use the secure message form on this page.') }}
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-5 space-y-3 text-xs dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="flex items-center gap-2 font-semibold">
                    <flux:icon name="heart" class="size-4 text-accent" />
                    <span>{{ __('System status') }}</span>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="opacity-70">{{ __('TOTP time synchronization') }}</span>
                        <flux:badge size="sm" color="emerald">{{ __('Accurate (±0s)') }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="opacity-70">{{ __('At-rest encryption') }}</span>
                        <flux:badge size="sm" color="emerald">{{ __('AES-256 active') }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="opacity-70">{{ __('External telemetry') }}</span>
                        <flux:badge size="sm" color="zinc">{{ __('Zero requests') }}</flux:badge>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Interactive Contact Form --}}
        <div class="lg:col-span-7">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/85 p-6 shadow-sm sm:p-8 dark:border-zinc-700 dark:bg-zinc-900/85">
                @if ($sent)
                    <div class="py-8 text-center space-y-4" role="status" aria-live="polite">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                            <flux:icon name="check" class="size-6" />
                        </div>
                        <flux:heading size="lg">{{ __('Message dispatched') }}</flux:heading>
                        <flux:text class="text-xs max-w-sm mx-auto opacity-75">
                            {{ __('Thank you! Your message has been recorded and delivered to the instance administrator.') }}
                        </flux:text>
                        <div>
                            <flux:button type="button" variant="outline" size="sm" wire:click="$set('sent', false)">
                                {{ __('Send another message') }}
                            </flux:button>
                        </div>
                    </div>
                @else
                    <form wire:submit="submit" class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <flux:input
                                wire:model="name"
                                :label="__('Your name')"
                                :placeholder="__('Jane Doe')"
                                required
                            />

                            <flux:input
                                wire:model="email"
                                type="email"
                                :label="__('Email address')"
                                :placeholder="__('jane@example.com')"
                                required
                            />
                        </div>

                        <flux:input
                            wire:model="subject"
                            :label="__('Subject')"
                            :placeholder="__('Inquiry, bug report, or feature request')"
                            required
                        />

                        <flux:textarea
                            wire:model="message"
                            :label="__('Message')"
                            rows="4"
                            :placeholder="__('Write your message here...')"
                            required
                        />

                        <div class="flex items-center justify-between pt-2">
                            <span class="text-[11px] opacity-60">
                                {{ __('Messages are saved directly to this instance database.') }}
                            </span>

                            <flux:button type="submit" variant="primary" icon="paper-airplane">
                                {{ __('Send message') }}
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </main>

    <x-guest-footer />
</div>
