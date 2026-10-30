<?php

declare(strict_types=1);

use App\Actions\Admin\BlockManagedUser;
use App\Actions\Admin\CreateManagedUser;
use App\Actions\Admin\DeleteManagedUser;
use App\Actions\Admin\UnblockManagedUser;
use App\Actions\Admin\UnlinkManagedUserOAuth;
use App\Actions\Admin\UpdateManagedUser;
use App\Enums\OAuthProvider;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Manage users')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showCreateModal = false;

    public string $createName = '';

    public string $createEmail = '';

    public string $createPassword = '';

    public bool $createIsAdmin = false;

    public bool $showEditModal = false;

    public ?int $editingUserId = null;

    public string $editName = '';

    public string $editEmail = '';

    public string $editPassword = '';

    public bool $editIsAdmin = false;

    public bool $showOAuthModal = false;

    public ?int $managingOAuthUserId = null;

    public function boot(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(boolean: $user === null || ! $user->is_admin || $user->is_blocked, code: 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->createName = '';
        $this->createEmail = '';
        $this->createPassword = '';
        $this->createIsAdmin = false;
        $this->showCreateModal = true;
    }

    public function createUser(CreateManagedUser $createManagedUser): void
    {
        /** @var array{createName: string, createEmail: string, createPassword: string, createIsAdmin: bool} $validated */
        $validated = Validator::make(
            data: [
                'createName' => $this->createName,
                'createEmail' => mb_strtolower(string: trim(string: $this->createEmail)),
                'createPassword' => $this->createPassword,
                'createIsAdmin' => $this->createIsAdmin,
            ],
            rules: [
                'createName' => ['required', 'string', 'max:255'],
                'createEmail' => ['required', 'string', 'email', 'max:255', Rule::unique(table: User::class, column: 'email')],
                'createPassword' => ['required', 'string', Password::default()],
                'createIsAdmin' => ['boolean'],
            ],
        )->validate();

        $createManagedUser->handle(
            name: $validated['createName'],
            email: $validated['createEmail'],
            password: $validated['createPassword'],
            isAdmin: (bool) $validated['createIsAdmin'],
        );

        $this->showCreateModal = false;
        unset($this->users);
        Flux::toast(variant: 'success', text: __('User account created.'));
    }

    public function openEditModal(int $userId): void
    {
        $this->resetErrorBag();
        $target = User::query()->findOrFail(id: $userId);

        $this->editingUserId = $target->id;
        $this->editName = $target->name;
        $this->editEmail = $target->email;
        $this->editPassword = '';
        $this->editIsAdmin = (bool) $target->is_admin;
        $this->showEditModal = true;
    }

    public function updateUser(UpdateManagedUser $updateManagedUser): void
    {
        if ($this->editingUserId === null) {
            return;
        }

        /** @var User $actor */
        $actor = Auth::user();

        /** @var array{editName: string, editEmail: string, editPassword: string|null, editIsAdmin: bool} $validated */
        $validated = Validator::make(
            data: [
                'editName' => $this->editName,
                'editEmail' => mb_strtolower(string: trim(string: $this->editEmail)),
                'editPassword' => $this->editPassword !== '' ? $this->editPassword : null,
                'editIsAdmin' => $this->editIsAdmin,
            ],
            rules: [
                'editName' => ['required', 'string', 'max:255'],
                'editEmail' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique(table: User::class, column: 'email')->ignore(id: $this->editingUserId),
                ],
                'editPassword' => ['nullable', 'string', Password::default()],
                'editIsAdmin' => ['boolean'],
            ],
        )->validate();

        $updateManagedUser->handle(
            actor: $actor,
            targetUserId: $this->editingUserId,
            name: $validated['editName'],
            email: $validated['editEmail'],
            isAdmin: (bool) $validated['editIsAdmin'],
            newPassword: $validated['editPassword'],
        );

        $this->showEditModal = false;
        $this->editingUserId = null;
        unset($this->users);
        Flux::toast(variant: 'success', text: __('User account updated.'));
    }

    public function blockUser(int $userId, BlockManagedUser $blockManagedUser): void
    {
        /** @var User $actor */
        $actor = Auth::user();

        $blockManagedUser->handle(actor: $actor, targetUserId: $userId);
        unset($this->users);
        Flux::toast(variant: 'warning', text: __('User account blocked and signed out.'));
    }

    public function unblockUser(int $userId, UnblockManagedUser $unblockManagedUser): void
    {
        $unblockManagedUser->handle(targetUserId: $userId);
        unset($this->users);
        Flux::toast(variant: 'success', text: __('User account unblocked.'));
    }

    public function deleteUser(int $userId, DeleteManagedUser $deleteManagedUser): void
    {
        /** @var User $actor */
        $actor = Auth::user();

        $deleteManagedUser->handle(actor: $actor, targetUserId: $userId);
        unset($this->users);
        Flux::toast(variant: 'success', text: __('User account deleted.'));
    }

    public function openOAuthModal(int $userId): void
    {
        $this->managingOAuthUserId = $userId;
        $this->showOAuthModal = true;
    }

    public function revokeOAuthAccount(int $oauthAccountId, UnlinkManagedUserOAuth $unlinkManagedUserOAuth): void
    {
        if ($this->managingOAuthUserId === null) {
            return;
        }

        /** @var User $actor */
        $actor = Auth::user();

        $unlinkManagedUserOAuth->handle(
            actor: $actor,
            targetUserId: $this->managingOAuthUserId,
            oauthAccountId: $oauthAccountId,
        );

        unset($this->users);
        unset($this->managingOAuthUser);

        Flux::toast(variant: 'success', text: __('OAuth connection revoked successfully.'));
    }

    #[Computed]
    public function managingOAuthUser(): ?User
    {
        if ($this->managingOAuthUserId === null) {
            return null;
        }

        return User::query()
            ->with(relations: ['oauthAccounts'])
            ->find(id: $this->managingOAuthUserId);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->withCount(relations: ['authenticators', 'oauthAccounts'])
            ->with(relations: ['oauthAccounts'])
            ->matching(term: $this->search)
            ->latest()
            ->paginate(perPage: 12);
    }
};
?>

<x-admin.nav
    :heading="__('Manage users')"
    :subheading="__('Create accounts, assign administrator access, or block compromised profiles.')"
>
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div class="w-full sm:max-w-sm">
            <flux:input
                wire:model.live.debounce.250ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name or email...')"
                clearable
            />
        </div>

        <flux:button type="button" variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('New user') }}
        </flux:button>
    </div>

    @error('delete')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/70 dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
        @forelse ($this->users as $managedUser)
            <div wire:key="managed-user-{{ $managedUser->id }}" class="flex flex-col justify-between gap-4 p-4 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="truncate text-sm font-semibold">{{ $managedUser->name }}</span>
                        @if ($managedUser->is_admin)
                            <flux:badge size="sm" color="amber">{{ __('Admin') }}</flux:badge>
                        @endif
                        @if ($managedUser->is_blocked)
                            <flux:badge size="sm" color="red">{{ __('Blocked') }}</flux:badge>
                        @endif
                        @if ($managedUser->id === auth()->id())
                            <flux:badge size="sm" color="zinc">{{ __('You') }}</flux:badge>
                        @endif
                        @foreach ($managedUser->oauthAccounts as $account)
                            <flux:badge size="sm" color="zinc" class="gap-1 font-mono text-[11px]">
                                @if ($account->provider === OAuthProvider::GitHub)
                                    <svg class="inline-block size-3 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                                    </svg>
                                @elseif ($account->provider === OAuthProvider::Google)
                                    <svg class="inline-block size-3" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.01 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                    </svg>
                                @endif
                                <span>{{ $account->provider->label() }}</span>
                            </flux:badge>
                        @endforeach
                    </div>
                    <div class="truncate text-xs opacity-70">
                        {{ $managedUser->email }} · {{ trans_choice(':count entry|:count entries', $managedUser->authenticators_count ?? 0, ['count' => $managedUser->authenticators_count ?? 0]) }} · {{ __('Theme: :palette', ['palette' => $managedUser->resolvedPalette()->label()]) }}
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="link"
                        wire:click="openOAuthModal({{ $managedUser->id }})"
                    >
                        {{ __('OAuth') }} ({{ $managedUser->oauth_accounts_count ?? 0 }})
                    </flux:button>

                    <flux:button type="button" size="sm" variant="ghost" wire:click="openEditModal({{ $managedUser->id }})">
                        {{ __('Edit') }}
                    </flux:button>

                    @if ($managedUser->id !== auth()->id())
                        @if ($managedUser->is_blocked)
                            <flux:button
                                type="button"
                                size="sm"
                                variant="ghost"
                                wire:click="unblockUser({{ $managedUser->id }})"
                            >
                                {{ __('Unblock') }}
                            </flux:button>
                        @else
                            <flux:button
                                type="button"
                                size="sm"
                                variant="ghost"
                                wire:click="blockUser({{ $managedUser->id }})"
                                wire:confirm="{{ __('Block this user and revoke active sessions?') }}"
                            >
                                {{ __('Block') }}
                            </flux:button>
                        @endif

                        <flux:button
                            type="button"
                            size="sm"
                            variant="danger"
                            icon="trash"
                            wire:click="deleteUser({{ $managedUser->id }})"
                            wire:confirm="{{ __('Permanently delete this user and all their authenticators?') }}"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm opacity-75">
                {{ __('No users match your search.') }}
            </div>
        @endforelse
    </div>

    <div>
        {{ $this->users->links() }}
    </div>

    <flux:modal wire:model.self="showCreateModal" class="md:w-96">
        <form wire:submit="createUser" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Create user account') }}</flux:heading>
                <flux:subheading>{{ __('Create a verified Vaulta account immediately.') }}</flux:subheading>
            </div>

            <flux:input wire:model="createName" :label="__('Name')" type="text" required />
            <flux:input wire:model="createEmail" :label="__('Email')" type="email" required />
            <flux:input wire:model="createPassword" :label="__('Temporary password')" type="password" viewable required />

            <flux:checkbox wire:model="createIsAdmin" :label="__('Grant administrator access')" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Create user') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditModal" class="md:w-96">
        <form wire:submit="updateUser" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Edit user account') }}</flux:heading>
                <flux:subheading>{{ __('Update account details or rotate password.') }}</flux:subheading>
            </div>

            <flux:input wire:model="editName" :label="__('Name')" type="text" required />
            <flux:input wire:model="editEmail" :label="__('Email')" type="email" required />
            <flux:input
                wire:model="editPassword"
                :label="__('New password (optional)')"
                type="password"
                viewable
                :placeholder="__('Leave blank to keep existing')"
            />

            <flux:checkbox wire:model="editIsAdmin" :label="__('Administrator access')" />
            @error('isAdmin')
                <flux:text class="text-xs text-red-500">{{ $message }}</flux:text>
            @enderror

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showEditModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
    <flux:modal wire:model.self="showOAuthModal" class="md:w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Manage OAuth connections') }}</flux:heading>
                <flux:subheading>
                    {{ __('Connected identity providers for :name (:email)', [
                        'name' => $this->managingOAuthUser?->name ?? '',
                        'email' => $this->managingOAuthUser?->email ?? '',
                    ]) }}
                </flux:subheading>
            </div>

            @if ($this->managingOAuthUser && empty($this->managingOAuthUser->password) && ($this->managingOAuthUser->oauthAccounts->count() ?? 0) <= 1)
                <flux:callout variant="warning" icon="exclamation-triangle" class="text-xs">
                    <flux:callout.text>
                        {{ __('This user has no password configured. Revoking their remaining OAuth connection will leave them unable to sign in until an administrator or password reset sets a credential.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/70 text-xs dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
                @forelse ($this->managingOAuthUser?->oauthAccounts ?? [] as $account)
                    <div wire:key="admin-oauth-account-{{ $account->id }}" class="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-950">
                                @if ($account->provider === OAuthProvider::GitHub)
                                    <svg class="size-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                                    </svg>
                                @elseif ($account->provider === OAuthProvider::Google)
                                    <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.01 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                    </svg>
                                @endif
                            </div>

                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm">{{ $account->provider->label() }}</span>
                                    <span class="text-zinc-500 font-mono text-[11px]">#{{ $account->provider_id }}</span>
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    <span>{{ $account->provider_email ?? $account->nickname ?? $account->name ?? __('Unknown identity') }}</span>
                                    @if ($account->created_at)
                                        <span class="opacity-60">· {{ __('Connected :time', ['time' => $account->created_at->diffForHumans()]) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <flux:button
                            type="button"
                            size="sm"
                            variant="danger"
                            icon="trash"
                            wire:click="revokeOAuthAccount({{ $account->id }})"
                            wire:confirm="{{ __('Revoke this :provider connection? The user will immediately lose sign-in access through this provider.', ['provider' => $account->provider->label()]) }}"
                        >
                            {{ __('Revoke') }}
                        </flux:button>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm opacity-70">
                        {{ __('No OAuth accounts are linked to this user.') }}
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showOAuthModal', false)">
                    {{ __('Close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin.nav>
