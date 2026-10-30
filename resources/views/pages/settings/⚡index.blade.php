<?php

declare(strict_types=1);

use App\Actions\Settings\RemoveUserAvatar;
use App\Actions\Settings\UpdateUserAvatar;
use App\Actions\Settings\UpdateUserProfile;
use App\Concerns\ProfileValidationRules;
use App\Enums\OAuthProvider;
use App\Models\OAuthAccount;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Settings — Vaulta')] class extends Component {
    use ProfileValidationRules;

    #[Url(as: 'tab')]
    public string $tab = 'profile';

    public string $name = '';

    public string $email = '';

    public ?string $avatarDataUrl = null;

    public function boot(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(boolean: $user === null || $user->is_blocked, code: 403);
    }

    public function mount(): void
    {
        if (! in_array(needle: $this->tab, haystack: ['profile', 'security', 'sessions', 'appearance', 'oauth'], strict: true)) {
            $this->tab = 'profile';
        }

        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function setTab(string $tab): void
    {
        if (in_array(needle: $tab, haystack: ['profile', 'security', 'sessions', 'appearance', 'oauth'], strict: true)) {
            $this->tab = $tab;
        }
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

        if (array_key_exists(key: 'avatarDataUrl', array: $validated) && filled($validated['avatarDataUrl'])) {
            try {
                $updateUserAvatar->handle(user: $user, dataUrl: (string) $validated['avatarDataUrl']);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->addError(name: $field, message: $message);
                    }
                }

                return;
            }
        }

        $this->resetErrorBag(field: 'avatarDataUrl');
        $this->avatarDataUrl = null;

        Flux::toast(variant: 'success', text: __('Profile information updated.'));
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

    #[Computed]
    public function activeSessionsCount(): int
    {
        if (! Schema::hasTable(table: 'sessions')) {
            return 1;
        }

        return DB::table(table: 'sessions')
            ->where(column: 'user_id', operator: '=', value: Auth::id())
            ->count();
    }

    #[Computed]
    public function passkeysCount(): int
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->passkeys()->count();
    }

    /**
     * @return Collection<int, OAuthAccount>
     */
    #[Computed]
    public function connectedOAuthAccounts(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->oauthAccounts()->get();
    }

    public function unlinkOAuth(string $provider): void
    {
        /** @var User $user */
        $user = Auth::user();

        $account = $user->oauthAccounts()
            ->where(column: 'provider', operator: '=', value: $provider)
            ->first();

        if ($account === null) {
            Flux::toast(variant: 'danger', text: __('Account is not connected.'));

            return;
        }

        $hasPassword = filled(value: $user->password);
        $totalOAuth = $user->oauthAccounts()->count();

        if ($totalOAuth <= 1 && ! $hasPassword) {
            Flux::toast(
                variant: 'danger',
                text: __('Cannot disconnect your only sign-in method. Please set a password first.'),
            );

            return;
        }

        $account->delete();
        unset($this->connectedOAuthAccounts);

        Flux::toast(variant: 'success', text: __('Account disconnected successfully.'));
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <div class="flex items-start max-md:flex-col">
        {{-- Desktop sidebar navigation --}}
        <div class="me-10 hidden w-[220px] pb-4 md:block">
            <flux:navlist aria-label="{{ __('Settings navigation') }}">
                <flux:navlist.item
                    icon="user-circle"
                    wire:click="setTab('profile')"
                    :current="$tab === 'profile'"
                    class="cursor-pointer"
                >
                    {{ __('Profile') }}
                </flux:navlist.item>

                <flux:navlist.item
                    icon="shield-check"
                    wire:click="setTab('security')"
                    :current="$tab === 'security'"
                    class="cursor-pointer"
                >
                    {{ __('Password & security') }}
                </flux:navlist.item>

                <flux:navlist.item
                    icon="computer-desktop"
                    wire:click="setTab('sessions')"
                    :current="$tab === 'sessions'"
                    class="cursor-pointer"
                >
                    {{ __('Active sessions') }}
                </flux:navlist.item>

                <flux:navlist.item
                    icon="paint-brush"
                    wire:click="setTab('appearance')"
                    :current="$tab === 'appearance'"
                    class="cursor-pointer"
                >
                    {{ __('Appearance') }}
                </flux:navlist.item>

                <flux:navlist.item
                    icon="link"
                    wire:click="setTab('oauth')"
                    :current="$tab === 'oauth'"
                    class="cursor-pointer"
                >
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
            >
                <flux:navbar class="-mb-px flex-nowrap shrink-0 gap-1">
                    <flux:navbar.item
                        icon="user-circle"
                        wire:click="setTab('profile')"
                        :current="$tab === 'profile'"
                        class="shrink-0 whitespace-nowrap cursor-pointer"
                    >
                        {{ __('Profile') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="shield-check"
                        wire:click="setTab('security')"
                        :current="$tab === 'security'"
                        class="shrink-0 whitespace-nowrap cursor-pointer"
                    >
                        {{ __('Password & security') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="computer-desktop"
                        wire:click="setTab('sessions')"
                        :current="$tab === 'sessions'"
                        class="shrink-0 whitespace-nowrap cursor-pointer"
                    >
                        {{ __('Active sessions') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="paint-brush"
                        wire:click="setTab('appearance')"
                        :current="$tab === 'appearance'"
                        class="shrink-0 whitespace-nowrap cursor-pointer"
                    >
                        {{ __('Appearance') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        icon="link"
                        wire:click="setTab('oauth')"
                        :current="$tab === 'oauth'"
                        class="shrink-0 whitespace-nowrap cursor-pointer"
                    >
                        {{ __('Connected accounts') }}
                    </flux:navbar.item>
                </flux:navbar>
            </div>
        </div>

        {{-- Content Pane --}}
        <div class="flex-1 self-stretch max-md:pt-4" style="view-transition-name: settings-pane;">
            @if ($tab === 'profile')
                <flux:heading>{{ __('Profile') }}</flux:heading>
                <flux:subheading>{{ __('Update your name, email address, and profile photo.') }}</flux:subheading>

                <div class="mt-5 w-full max-w-3xl space-y-6">
                    {{-- Avatar Uploader --}}
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

                    {{-- Profile Information Form --}}
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
                        <div class="max-w-lg pt-4 border-t border-zinc-200 dark:border-zinc-800">
                            <livewire:pages::settings.delete-user-form />
                        </div>
                    @endif
                </div>
            @elseif ($tab === 'security')
                <flux:heading>{{ __('Password & security') }}</flux:heading>
                <flux:subheading>{{ __('Manage your master password, two-factor authentication, and WebAuthn passkeys.') }}</flux:subheading>

                <div class="mt-5 w-full max-w-2xl space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-6 space-y-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <div class="font-semibold text-sm">{{ __('Authentication & Credentials') }}</div>
                                <div class="text-xs opacity-75">{{ __('Password changes and passkey registrations are managed in the security center.') }}</div>
                            </div>
                            <flux:badge size="sm" color="{{ auth()->user()->two_factor_secret ? 'emerald' : 'amber' }}">
                                {{ auth()->user()->two_factor_secret ? __('2FA Enabled') : __('2FA Inactive') }}
                            </flux:badge>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                            <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="text-xs opacity-70">{{ __('Two-Factor Auth') }}</div>
                                <div class="mt-1 font-semibold text-sm">
                                    {{ auth()->user()->two_factor_secret ? __('Active (TOTP)') : __('Not configured') }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="text-xs opacity-70">{{ __('Registered Passkeys') }}</div>
                                <div class="mt-1 font-semibold text-sm">
                                    {{ $this->passkeysCount }} {{ __('passkeys') }}
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <flux:button
                                variant="primary"
                                icon="shield-check"
                                :href="route('security.edit')"
                                wire:navigate.hover
                            >
                                {{ __('Open security center') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'sessions')
                <flux:heading>{{ __('Active sessions') }}</flux:heading>
                <flux:subheading>{{ __('Manage and revoke your active browser sessions across all devices.') }}</flux:subheading>

                <div class="mt-5 w-full max-w-2xl space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-6 space-y-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <div class="font-semibold text-sm">{{ __('Browser Sessions') }}</div>
                                <div class="text-xs opacity-75">{{ __('Monitor signed-in devices and revoke stale or untrusted sessions.') }}</div>
                            </div>
                            <flux:badge size="sm" color="emerald">
                                {{ $this->activeSessionsCount }} {{ __('active') }}
                            </flux:badge>
                        </div>

                        <div class="pt-2">
                            <flux:button
                                variant="primary"
                                icon="computer-desktop"
                                :href="route('sessions.edit')"
                                wire:navigate.hover
                            >
                                {{ __('Manage active sessions') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'appearance')
                <flux:heading>{{ __('Appearance') }}</flux:heading>
                <flux:subheading>{{ __('Customize the visual style and tactile Ferrite palette of Vaulta.') }}</flux:subheading>

                <div class="mt-5 w-full max-w-3xl space-y-6">
                    <livewire:settings.palette-picker />
                </div>
            @elseif ($tab === 'oauth')
                <flux:heading>{{ __('Connected accounts') }}</flux:heading>
                <flux:subheading>{{ __('Link your third-party social accounts to quickly sign in to your vault with one click.') }}</flux:subheading>

                <div class="mt-5 w-full max-w-3xl space-y-6">
                    <div class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/70 text-xs dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
                        @foreach (OAuthProvider::cases() as $provider)
                            @php
                                $connected = $this->connectedOAuthAccounts->firstWhere('provider', $provider);
                            @endphp
                            <div wire:key="oauth-provider-{{ $provider->value }}" class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center">
                                <div class="flex items-center gap-3.5">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-950">
                                        @if ($provider === OAuthProvider::GitHub)
                                            <svg class="size-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                                            </svg>
                                        @elseif ($provider === OAuthProvider::Google)
                                            <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                                <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.01 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                            </svg>
                                        @endif
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-sm">{{ $provider->label() }}</span>
                                            @if ($connected)
                                                <flux:badge size="xs" color="emerald">{{ __('Connected') }}</flux:badge>
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                            @if ($connected)
                                                <span>{{ $connected->provider_email ?? $connected->name ?? $connected->nickname ?? __('Connected') }}</span>
                                                <span class="opacity-60">· {{ $connected->created_at?->diffForHumans() }}</span>
                                            @else
                                                <span>{{ __('Sign in quickly with your :provider account', ['provider' => $provider->label()]) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    @if ($connected)
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            wire:click="unlinkOAuth('{{ $provider->value }}')"
                                            wire:confirm="{{ __('Disconnect your :provider account? You will no longer be able to log in using it.', ['provider' => $provider->label()]) }}"
                                        >
                                            {{ __('Disconnect') }}
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            :href="route('oauth.redirect', ['provider' => $provider->value])"
                                            wire:navigate="false"
                                        >
                                            {{ __('Connect') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
