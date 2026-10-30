<?php

declare(strict_types=1);

use App\Actions\Settings\RemoveUserAvatar;
use App\Actions\Settings\UpdateUserAvatar;
use App\Actions\Settings\UpdateUserProfile;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Transition;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public ?string $avatarDataUrl = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfileInformation(
        UpdateUserProfile $updateUserProfile,
        UpdateUserAvatar $updateUserAvatar,
    ): void {
        /** @var User $user */
        $user = Auth::user();

        $validated = $this->validate(rules: [
            ...$this->profileRules(userId: $user->id),
            'avatarDataUrl' => ['nullable', 'string', 'max:1400000'],
        ]);

        $updateUserProfile->handle(
            user: $user,
            name: $validated['name'],
            email: $validated['email'],
        );

        if (filled($validated['avatarDataUrl'] ?? null) && is_string($validated['avatarDataUrl'])) {
            try {
                $updateUserAvatar->handle(user: $user, dataUrl: $validated['avatarDataUrl']);
                $this->avatarDataUrl = null;
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->addError(name: $field, message: $message);
                    }
                }

                return;
            }
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function updateAvatar(string $dataUrl, UpdateUserAvatar $updateUserAvatar): void
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $updateUserAvatar->handle(user: $user, dataUrl: $dataUrl);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(name: $field, message: $message);
                }
            }

            return;
        }

        $this->resetErrorBag(field: 'avatarDataUrl');
        $this->avatarDataUrl = null;

        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    public function removeAvatar(RemoveUserAvatar $removeUserAvatar): void
    {
        /** @var User $user */
        $user = Auth::user();

        $removeUserAvatar->handle(user: $user);
        $this->avatarDataUrl = null;

        Flux::toast(variant: 'success', text: __('Profile photo removed.'));
    }

    public function resendVerificationNotification(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route(name: 'vault', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash(key: 'status', value: 'verification-link-sent');
    }

    #[Transition(type: 'forward')]
    public function goToSecurity(): void
    {
        $this->redirect(url: route(name: 'security.edit'), navigate: true);
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name, email address, and profile photo.')">
        <div
            x-data="{
                avatarBusy: false,
                async uploadAvatar(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    if (file.size > 10_000_000) {
                        event.target.value = '';
                        return;
                    }
                    this.avatarBusy = true;
                    let bitmap;
                    try {
                        bitmap = await createImageBitmap(file);
                        const scale = Math.min(1, 512 / Math.max(bitmap.width, bitmap.height));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.85));
                        const dataUrl = await new Promise((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onload = () => resolve(reader.result);
                            reader.onerror = reject;
                            reader.readAsDataURL(blob);
                        });
                        await $wire.updateAvatar(dataUrl);
                    } finally {
                        bitmap?.close();
                        this.avatarBusy = false;
                        event.target.value = '';
                    }
                }
            }"
            class="my-6 flex items-center gap-4 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/70"
        >
            @if (auth()->user()->avatar)
                <img src="{{ auth()->user()->avatar }}" alt="" class="size-14 shrink-0 rounded-full object-cover ring-2 ring-accent/40" />
            @else
                <div class="grid size-14 shrink-0 place-items-center rounded-full bg-accent font-serif text-base font-bold text-accent-foreground">
                    {{ auth()->user()->initials() }}
                </div>
            @endif

            <div class="min-w-0 flex-1 space-y-2">
                <div class="text-sm font-semibold">{{ __('Profile photo') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-1.5 text-xs font-semibold hover:border-accent dark:border-zinc-700 dark:bg-zinc-950">
                        <span>{{ __('Upload photo') }}</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" x-on:change="uploadAvatar($event)" />
                    </label>

                    @if (auth()->user()->avatar)
                        <flux:button type="button" size="sm" variant="ghost" wire:click="removeAvatar">
                            {{ __('Remove') }}
                        </flux:button>
                    @endif
                </div>
                @error('avatarDataUrl')
                    <p class="text-xs text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full max-w-lg space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !text-green-600 !dark:text-green-400">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-profile-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <div class="max-w-lg">
                <livewire:pages::settings.delete-user-form />
            </div>
        @endif
    </x-pages::settings.layout>
</section>
