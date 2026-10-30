<?php

declare(strict_types=1);

use App\Actions\Admin\DeleteManagedAuthenticator;
use App\Actions\Admin\UpdateAuthenticatorMetadata;
use App\Models\Authenticator;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Manage authenticators')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showEditModal = false;

    public ?int $editingAuthenticatorId = null;

    public string $editIssuer = '';

    public string $editAccount = '';

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

    public function openEditModal(int $authenticatorId): void
    {
        $this->resetErrorBag();
        $entry = Authenticator::query()
            ->withoutSecrets()
            ->findOrFail(id: $authenticatorId);

        $this->editingAuthenticatorId = $entry->id;
        $this->editIssuer = $entry->issuer;
        $this->editAccount = $entry->account;
        $this->showEditModal = true;
    }

    public function saveMetadata(UpdateAuthenticatorMetadata $updateAuthenticatorMetadata): void
    {
        if ($this->editingAuthenticatorId === null) {
            return;
        }

        /** @var array{editIssuer: string, editAccount: string} $validated */
        $validated = Validator::make(
            data: [
                'editIssuer' => $this->editIssuer,
                'editAccount' => $this->editAccount,
            ],
            rules: [
                'editIssuer' => ['required', 'string', 'max:120'],
                'editAccount' => ['required', 'string', 'max:160'],
            ],
        )->validate();

        $updateAuthenticatorMetadata->handle(
            authenticatorId: $this->editingAuthenticatorId,
            issuer: $validated['editIssuer'],
            account: $validated['editAccount'],
        );

        $this->showEditModal = false;
        $this->editingAuthenticatorId = null;
        unset($this->authenticators);
        Flux::toast(variant: 'success', text: __('Authenticator metadata updated.'));
    }

    public function deleteAuthenticator(int $authenticatorId, DeleteManagedAuthenticator $deleteManagedAuthenticator): void
    {
        $deleteManagedAuthenticator->handle(authenticatorId: $authenticatorId);
        unset($this->authenticators);
        Flux::toast(variant: 'success', text: __('Authenticator removed.'));
    }

    /**
     * @return LengthAwarePaginator<int, Authenticator>
     */
    #[Computed]
    public function authenticators(): LengthAwarePaginator
    {
        return Authenticator::query()
            ->withoutSecrets()
            ->with(relations: 'user:id,name,email')
            ->matchingAdminSearch(term: $this->search)
            ->latest()
            ->paginate(perPage: 12);
    }
};
?>

<x-admin.nav
    :heading="__('Authenticator metadata')"
    :subheading="__('Inspect authenticator labels and ownership without decrypting or exposing TOTP secrets.')"
>
    <div class="w-full sm:max-w-sm">
        <flux:input
            wire:model.live.debounce.250ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search issuer, account, or owner...')"
            clearable
        />
    </div>

    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/70 dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
        @forelse ($this->authenticators as $entry)
            <div wire:key="admin-authenticator-{{ $entry->id }}" class="flex flex-col justify-between gap-4 p-4 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <x-ui.issuer-icon :issuer="$entry->issuer" size="sm" />
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.tag-dot :color="$entry->tagColor()" />
                            <span class="truncate text-sm font-semibold">{{ $entry->issuer }}</span>
                            <span class="truncate font-mono text-xs opacity-75">{{ $entry->account }}</span>
                            @if ($entry->is_favorite)
                                <flux:badge size="sm" color="amber">{{ __('Favorite') }}</flux:badge>
                            @endif
                        </div>
                        <div class="truncate text-xs opacity-70">
                            {{ __('Owner: :name (:email)', ['name' => $entry->user?->name ?? __('Deleted user'), 'email' => $entry->user?->email ?? '—']) }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button type="button" size="sm" variant="ghost" wire:click="openEditModal({{ $entry->id }})">
                        {{ __('Edit labels') }}
                    </flux:button>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="danger"
                        icon="trash"
                        wire:click="deleteAuthenticator({{ $entry->id }})"
                        wire:confirm="{{ __('Delete this authenticator entry permanently?') }}"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm opacity-75">
                {{ __('No authenticators match your search.') }}
            </div>
        @endforelse
    </div>

    <div>
        {{ $this->authenticators->links() }}
    </div>

    <flux:modal wire:model.self="showEditModal" class="md:w-96">
        <form wire:submit="saveMetadata" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Edit authenticator labels') }}</flux:heading>
                <flux:subheading>{{ __('Only display labels are modified; the encrypted secret remains untouched.') }}</flux:subheading>
            </div>

            <flux:input wire:model="editIssuer" :label="__('Issuer')" type="text" required />
            <flux:input wire:model="editAccount" :label="__('Account')" type="text" required />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showEditModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Save labels') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-admin.nav>
