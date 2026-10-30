<?php

declare(strict_types=1);

use App\Actions\Settings\RevokeOtherUserSessions;
use App\Actions\Settings\RevokeUserSession;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Transition;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Active sessions')] class extends Component {
    public function revokeSession(string $token, RevokeUserSession $revokeUserSession): void
    {
        /** @var User $user */
        $user = Auth::user();

        $revoked = $revokeUserSession->handle(
            user: $user,
            token: $token,
            currentSessionId: Session::getId(),
        );

        if ($revoked) {
            unset($this->sessions);
            Flux::toast(variant: 'success', text: __('Session signed out.'));
        }
    }

    public function revokeOtherSessions(RevokeOtherUserSessions $revokeOtherUserSessions): void
    {
        /** @var User $user */
        $user = Auth::user();

        $revokeOtherUserSessions->handle(
            user: $user,
            currentSessionId: Session::getId(),
        );

        unset($this->sessions);
        Flux::toast(variant: 'success', text: __('All other sessions have been signed out.'));
    }

    #[Transition(type: 'forward')]
    public function goToAppearance(): void
    {
        $this->redirect(url: route(name: 'appearance.edit'), navigate: true);
    }

    #[Computed]
    public function databaseSessionsEnabled(): bool
    {
        $table = config(key: 'session.table', default: 'sessions');

        return config(key: 'session.driver') === 'database'
            && is_string($table)
            && Schema::hasTable(table: $table);
    }

    /**
     * @return list<array{token: string, ip: string|null, agent: string, last_active: string, current: bool}>
     */
    #[Computed]
    public function sessions(): array
    {
        if (! $this->databaseSessionsEnabled) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();
        $currentId = Session::getId();

        /** @var string $table */
        $table = config(key: 'session.table', default: 'sessions');

        $rows = DB::table(table: $table)
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->orderByDesc(column: 'last_activity')
            ->get(columns: ['id', 'ip_address', 'user_agent', 'last_activity']);

        $result = [];
        foreach ($rows as $row) {
            $id = (string) ($row->id ?? '');
            $result[] = [
                'token' => RevokeUserSession::tokenFor(sessionId: $id),
                'ip' => is_string($row->ip_address ?? null) ? $row->ip_address : null,
                'agent' => mb_substr(string: (string) ($row->user_agent ?? 'Unknown browser'), start: 0, length: 180),
                'last_active' => now()->setTimestamp(timestamp: (int) ($row->last_activity ?? time()))->diffForHumans(),
                'current' => $id === $currentId,
            ];
        }

        return $result;
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Active sessions') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Active sessions')" :subheading="__('Review signed-in browsers and revoke access on lost or shared devices.')">
        @if (! $this->databaseSessionsEnabled)
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('Session listing unavailable') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Configure SESSION_DRIVER=database to inspect and revoke individual browser sessions.') }}
                </flux:callout.text>
            </flux:callout>
        @else
            <div class="space-y-4">
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/70 dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
                    @forelse ($this->sessions as $device)
                        <div wire:key="session-{{ $device['token'] }}" class="flex items-center justify-between gap-4 p-4">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold">{{ $device['agent'] }}</span>
                                    @if ($device['current'])
                                        <flux:badge size="sm" color="emerald">{{ __('This device') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="text-xs opacity-70">
                                    {{ $device['ip'] ?? __('Unknown IP') }} · {{ __('Last active :time', ['time' => $device['last_active']]) }}
                                </div>
                            </div>

                            @if (! $device['current'])
                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    wire:click="revokeSession('{{ $device['token'] }}')"
                                    wire:confirm="{{ __('Sign out this device?') }}"
                                >
                                    {{ __('Sign out') }}
                                </flux:button>
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center text-sm opacity-75">
                            {{ __('No active database sessions recorded.') }}
                        </div>
                    @endforelse
                </div>

                @if (count($this->sessions) > 1)
                    <div>
                        <flux:button
                            type="button"
                            variant="danger"
                            wire:click="revokeOtherSessions"
                            wire:confirm="{{ __('Sign out all other devices?') }}"
                        >
                            {{ __('Sign out all other devices') }}
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </x-pages::settings.layout>
</section>
