<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        /** @var User $user */
        $user = Auth::user();
        $enableTwoFactorAuthentication(user: $user);

        $this->loadSetupData();
    }

    private function loadSetupData(): void
    {
        /** @var User|null $user */
        $user = Auth::user()?->fresh();

        try {
            if ($user === null || ! $user->two_factor_secret) {
                throw new RuntimeException(message: 'Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $decrypted = decrypt(value: $user->two_factor_secret);
            $this->manualSetupKey = is_string($decrypted) ? $decrypted : '';
        } catch (Throwable) {
            $this->addError(name: 'setupData', message: 'Failed to fetch setup data.');
            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;
            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch(event: 'two-factor-enabled');
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        /** @var User $user */
        $user = Auth::user();
        $confirmTwoFactorAuthentication(user: $user, code: $this->code);

        $this->setupComplete = true;
        $this->closeModal();
        $this->dispatch(event: 'two-factor-enabled');
    }

    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');
        $this->resetErrorBag();
    }

    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();
    }

    /**
     * @return array{title: string, description: string, buttonText: string}
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
};
?>

<flux:modal
    name="two-factor-setup-modal"
    class="max-w-md md:min-w-md"
    @close="closeModal"
>
    <div class="space-y-6">
        <div class="flex flex-col items-center space-y-4">
            <div class="w-auto rounded-full border border-zinc-200 bg-zinc-50 p-2.5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <flux:icon name="qr-code" class="size-6 text-accent" />
            </div>

            <div class="space-y-2 text-center">
                <flux:heading size="lg">{{ $this->modalConfig['title'] }}</flux:heading>
                <flux:text>{{ $this->modalConfig['description'] }}</flux:text>
            </div>
        </div>

        @if ($showVerificationStep)
            <div class="space-y-6">
                <div
                    class="flex flex-col items-center justify-center space-y-3"
                    x-data
                    x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                >
                    <flux:otp
                        name="code"
                        wire:model="code"
                        length="6"
                        label="OTP Code"
                        label:sr-only
                        class="mx-auto"
                    />
                </div>

                <div class="flex items-center space-x-3">
                    <flux:button
                        variant="outline"
                        class="flex-1"
                        wire:click="resetVerification"
                    >
                        {{ __('Back') }}
                    </flux:button>

                    <flux:button
                        variant="primary"
                        class="flex-1"
                        wire:click="confirmTwoFactor"
                        x-bind:disabled="$wire.code.length < 6"
                    >
                        {{ __('Confirm') }}
                    </flux:button>
                </div>
            </div>
        @else
            @error('setupData')
                <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
            @enderror

            <div class="flex justify-center">
                <div class="relative aspect-square w-64 overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700">
                    @empty($qrCodeSvg)
                        <div class="absolute inset-0 flex animate-pulse items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon.loading />
                        </div>
                    @else
                        <div class="flex h-full items-center justify-center p-4">
                            <div class="rounded bg-white p-3">
                                {!! $qrCodeSvg !!}
                            </div>
                        </div>
                    @endempty
                </div>
            </div>

            <div>
                <flux:button
                    :disabled="$errors->has('setupData')"
                    variant="primary"
                    class="w-full"
                    wire:click="showVerificationIfNecessary"
                >
                    {{ $this->modalConfig['buttonText'] }}
                </flux:button>
            </div>

            <div class="space-y-4">
                <div class="relative flex w-full items-center justify-center">
                    <div class="absolute inset-0 top-1/2 h-px w-full bg-zinc-200 dark:bg-zinc-700"></div>
                    <span class="relative bg-zinc-50 px-2 text-xs opacity-75 dark:bg-zinc-900">
                        {{ __('or, enter the code manually') }}
                    </span>
                </div>

                <div
                    class="flex items-center space-x-2"
                    x-data="{
                        copied: false,
                        async copy() {
                            try {
                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {}
                        }
                    }"
                >
                    <div class="flex w-full items-stretch rounded-xl border border-zinc-200 dark:border-zinc-700">
                        @empty($manualSetupKey)
                            <div class="flex w-full items-center justify-center bg-zinc-100 p-3 dark:bg-zinc-800">
                                <flux:icon.loading variant="mini" />
                            </div>
                        @else
                            <input
                                type="text"
                                readonly
                                value="{{ $manualSetupKey }}"
                                class="w-full bg-transparent p-3 font-mono text-sm outline-none"
                            />

                            <button
                                type="button"
                                @click="copy()"
                                class="cursor-pointer border-l border-zinc-200 px-3 transition-colors dark:border-zinc-700"
                            >
                                <flux:icon.document-duplicate x-show="!copied" variant="outline" />
                                <flux:icon.check x-show="copied" variant="solid" class="text-emerald-500" />
                            </button>
                        @endempty
                    </div>
                </div>
            </div>
        @endif
    </div>
</flux:modal>
