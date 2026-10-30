<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Authenticator Vault')] class extends Component {
    public bool $openAddInitially = false;

    public function boot(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(boolean: $user?->is_blocked === true, code: 403);
    }

    public function mount(): void
    {
        $this->openAddInitially = request()->boolean(key: 'add');
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <flux:heading size="xl" level="1">{{ __('Your codes, in sync.') }}</flux:heading>
            <flux:subheading>{{ __('Private RFC 6238 two-factor authenticator vault with encrypted storage.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button
                type="button"
                variant="primary"
                icon="plus"
                wire:click="$dispatch('open-add-entry-modal')"
                data-test="open-add-entry-button"
            >
                {{ __('Add account') }}
            </flux:button>
        </div>
    </div>

    <livewire:vault.entry-list />

    <livewire:vault.add-entry :open-initially="$openAddInitially" />
</div>
