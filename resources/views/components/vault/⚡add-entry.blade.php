<?php

declare(strict_types=1);

use App\Actions\Entries\AddEntry;
use App\Actions\Entries\ParseOtpAuthUri;
use App\Enums\TagColor;
use App\Models\User;
use App\Support\IssuerBrand;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $showModal = false;

    public string $setupUri = '';

    public string $issuer = '';

    public string $account = '';

    public string $secret = '';

    public string $color = 'zinc';

    public function selectProvider(string $provider): void
    {
        $this->issuer = $provider;
    }

    #[On('icon-selected')]
    public function handleIconSelected(string $provider): void
    {
        $this->issuer = $provider;
    }

    public function mount(bool $openInitially = false): void
    {
        $this->showModal = $openInitially;
    }

    #[On('open-add-entry-modal')]
    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function parseSetupUri(ParseOtpAuthUri $parseOtpAuthUri): bool
    {
        $this->resetErrorBag(field: 'setupUri');

        try {
            $payload = $parseOtpAuthUri->handle(rawUri: $this->setupUri);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(name: $field, message: $message);
                }
            }

            return false;
        }

        $this->issuer = $payload->issuer;
        $this->account = $payload->account;
        $this->secret = $payload->secret;

        Flux::toast(variant: 'success', text: __('Setup link imported — review the details below.'));

        return true;
    }

    public function save(AddEntry $addEntry): void
    {
        /** @var User $user */
        $user = Auth::user();
        $throttleKey = 'vault-add:'.$user->id;

        if (RateLimiter::tooManyAttempts(key: $throttleKey, maxAttempts: 10)) {
            $this->addError(
                name: 'issuer',
                message: 'Too many accounts added. Please wait a minute before trying again.',
            );

            return;
        }

        $validated = $this->validate(rules: [
            'issuer' => ['required', 'string', 'max:100'],
            'account' => ['required', 'string', 'max:180'],
            'secret' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z2-7\s=-]+$/'],
            'color' => ['required', 'string', Rule::enum(type: TagColor::class)],
        ]);

        try {
            $addEntry->handle(
                user: $user,
                issuer: $validated['issuer'],
                account: $validated['account'],
                secret: $validated['secret'],
                color: TagColor::from(value: $validated['color']),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(name: $field, message: $message);
                }
            }

            return;
        }

        RateLimiter::hit(key: $throttleKey, decaySeconds: 60);

        $this->reset(['setupUri', 'issuer', 'account', 'secret']);
        $this->color = TagColor::default()->value;
        $this->showModal = false;

        $this->dispatch(event: 'entry-added');
        Flux::toast(variant: 'success', text: __('Account added to your vault.'));
    }
};
?>

<div
    x-data="{
        qrImportStatus: '',
        async importQrImage(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.qrImportStatus = '';

            if (file.size > 10_000_000) {
                this.qrImportStatus = 'Choose an image smaller than 10 MB.';
                event.target.value = '';
                return;
            }

            if (!file.type.startsWith('image/')) {
                this.qrImportStatus = 'Choose an image containing a TOTP QR code.';
                event.target.value = '';
                return;
            }

            if (!('BarcodeDetector' in window)) {
                this.qrImportStatus = 'QR image scanning is not supported in this browser. Paste the setup link below instead.';
                event.target.value = '';
                return;
            }

            let bitmap;
            try {
                bitmap = await createImageBitmap(file);
                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                const [result] = await detector.detect(bitmap);
                if (!result?.rawValue) {
                    this.qrImportStatus = 'No QR code was found. Choose a clearer image or paste the setup link below.';
                    return;
                }
                await $wire.set('setupUri', result.rawValue);
                const imported = await $wire.parseSetupUri();
                this.qrImportStatus = imported
                    ? 'QR code imported. Review the details before saving.'
                    : 'This QR code is not a supported TOTP setup code.';
            } catch (error) {
                this.qrImportStatus = 'The image could not be scanned. Choose another image or paste the setup link below.';
            } finally {
                bitmap?.close();
                event.target.value = '';
            }
        }
    }"
>
    <flux:modal wire:model="showModal" wire:transition="modal" class="w-full max-w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add an account') }}</flux:heading>
                <flux:subheading>{{ __('Import a QR image, paste an otpauth:// setup link, or enter your Base32 setup key manually.') }}</flux:subheading>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-zinc-100/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                <label for="setup-qr-image" class="block text-xs font-semibold">
                    {{ __('Import from a QR image') }}
                </label>
                <input
                    id="setup-qr-image"
                    type="file"
                    accept="image/*"
                    x-on:change="importQrImage($event)"
                    class="mt-2 block w-full cursor-pointer rounded-lg border border-zinc-300 bg-zinc-50 text-xs file:me-3 file:cursor-pointer file:border-0 file:bg-accent file:px-3 file:py-2 file:text-xs file:font-semibold file:text-accent-foreground dark:border-zinc-700 dark:bg-zinc-950"
                />
                <p class="mt-1.5 text-[11px] opacity-75">
                    {{ __('Scanned locally in your browser — never uploaded to the server.') }}
                </p>
                <p x-show="qrImportStatus" x-cloak x-text="qrImportStatus" role="status" aria-live="polite" class="mt-1.5 text-xs font-medium"></p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-zinc-100/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                <label for="setup-uri-input" class="block text-xs font-semibold">
                    {{ __('Quick setup with a link') }}
                </label>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <flux:textarea
                        id="setup-uri-input"
                        wire:model="setupUri"
                        rows="2"
                        placeholder="otpauth://totp/GitHub:you@example.com?secret=JBSWY3DPEHPK3PXP&issuer=GitHub"
                    />
                    <flux:button type="button" wire:click="parseSetupUri" variant="filled" class="shrink-0">
                        {{ __('Fill details') }}
                    </flux:button>
                </div>
                @error('setupUri')
                    <p class="mt-1.5 text-xs text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Account icon') }}</flux:label>
                    <livewire:vault.icon-picker wire:model="issuer" />
                </flux:field>

                <flux:input
                    wire:model.live.debounce.150ms="issuer"
                    :label="__('Service name')"
                    placeholder="e.g. GitHub"
                    required
                    data-test="add-entry-issuer"
                />

                <flux:input
                    wire:model="account"
                    :label="__('Account label')"
                    placeholder="you@example.com"
                    required
                    data-test="add-entry-account"
                />

                <flux:field>
                    <flux:label>{{ __('Secret key') }}</flux:label>
                    <flux:input
                        wire:model="secret"
                        autocomplete="off"
                        placeholder="JBSW Y3DP EHPK 3PXP"
                        class="font-mono tracking-wider"
                        required
                        data-test="add-entry-secret"
                    />
                    <flux:description>{{ __('Base32 setup key with at least 16 characters. Encrypted at rest.') }}</flux:description>
                    <flux:error name="secret" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Colour tag') }}</flux:label>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Colour tag') }}">
                        @foreach (TagColor::cases() as $tagCase)
                            <button
                                type="button"
                                wire:key="add-tag-{{ $tagCase->value }}"
                                role="radio"
                                aria-checked="{{ $color === $tagCase->value ? 'true' : 'false' }}"
                                wire:click="$set('color', '{{ $tagCase->value }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition aria-checked:border-accent aria-checked:ring-2 aria-checked:ring-accent/40 {{ $tagCase->ringClass() }}"
                            >
                                <span class="size-2.5 rounded-full {{ $tagCase->dotClass() }}" aria-hidden="true"></span>
                                <span>{{ $tagCase->label() }}</span>
                            </button>
                        @endforeach
                    </div>
                    <flux:error name="color" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="plus" data-test="save-entry-button">
                        {{ __('Save account') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
