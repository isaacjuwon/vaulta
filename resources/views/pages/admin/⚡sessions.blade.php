<?php

declare(strict_types=1);

use App\Actions\Admin\RevokeManagedSession;
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
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Manage sessions')] class extends Component {
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function boot(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(boolean: $user === null || ! $user->is_admin || $user->is_blocked, code: 403);
    }

    public function revokeSession(string $token, RevokeManagedSession $revokeManagedSession): void
    {
        $revoked = $revokeManagedSession->handle(
            token: $token,
            currentSessionId: Session::getId(),
        );

        if ($revoked) {
            unset($this->sessions);
            Flux::toast(variant: 'success', text: __('Browser session revoked.'));
        }
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
     * @return list<array{
     *     token: string,
     *     user_name: string,
     *     user_email: string,
     *     ip: string|null,
     *     agent: string,
     *     last_active: string,
     *     current: bool
     * }>
     */
    #[Computed]
    public function sessions(): array
    {
        if (! $this->databaseSessionsEnabled) {
            return [];
        }

        /** @var string $table */
        $table = config(key: 'session.table', default: 'sessions');
        $currentId = Session::getId();
        $term = mb_strtolower(string: trim(string: $this->search));

        $rows = DB::table(table: $table)
            ->leftJoin(table: 'users', first: "{$table}.user_id", operator: '=', second: 'users.id')
            ->orderByDesc(column: "{$table}.last_activity")
            ->limit(value: 100)
            ->get(columns: [
                "{$table}.id",
                "{$table}.ip_address",
                "{$table}.user_agent",
                "{$table}.last_activity",
                'users.name as user_name',
                'users.email as user_email',
            ]);

        $items = [];
        foreach ($rows as $row) {
            $id = (string) ($row->id ?? '');
            $userName = is_string($row->user_name ?? null) && $row->user_name !== '' ? $row->user_name : __('Guest session');
            $userEmail = is_string($row->user_email ?? null) ? $row->user_email : '';
            $ip = is_string($row->ip_address ?? null) ? $row->ip_address : null;
            $agent = mb_substr(string: (string) ($row->user_agent ?? 'Unknown browser'), start: 0, length: 180);

            if ($term !== '') {
                $haystack = mb_strtolower(string: "{$userName} {$userEmail} {$ip} {$agent}");
                if (! str_contains(haystack: $haystack, needle: $term)) {
                    continue;
                }
            }

            $items[] = [
                'token' => RevokeUserSession::tokenFor(sessionId: $id),
                'user_name' => $userName,
                'user_email' => $userEmail,
                'ip' => $ip,
                'agent' => $agent,
                'last_active' => now()->setTimestamp(timestamp: (int) ($row->last_activity ?? time()))->diffForHumans(),
                'current' => $id === $currentId,
            ];
        }

        return $items;
    }
};
?>

<x-admin.nav
    :heading="__('Active instance sessions')"
    :subheading="__('Inspect active browser sessions across users and revoke suspicious devices.')"
>
    @if (! $this->databaseSessionsEnabled)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('Database session driver required') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Configure SESSION_DRIVER=database to inspect and revoke sessions across the instance.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="w-full sm:max-w-sm">
            <flux:input
                wire:model.live.debounce.250ms="search"
                icon="magnifying-glass"
                :placeholder="__('Filter by user, IP, or browser...')"
                clearable
            />
        </div>

        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/70 dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
            @forelse ($this->sessions as $device)
                <div wire:key="admin-session-{{ $device['token'] }}" class="flex flex-col justify-between gap-4 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold">{{ $device['user_name'] }}</span>
                            @if ($device['user_email'] !== '')
                                <span class="text-xs opacity-70">({{ $device['user_email'] }})</span>
                            @endif
                            @if ($device['current'])
                                <flux:badge size="sm" color="emerald">{{ __('Current session') }}</flux:badge>
                            @endif
                        </div>
                        <div class="truncate text-xs opacity-75">
                            {{ $device['agent'] }}
                        </div>
                        <div class="text-xs opacity-65">
                            {{ $device['ip'] ?? __('Unknown IP') }} · {{ __('Last active :time', ['time' => $device['last_active']]) }}
                        </div>
                    </div>

                    @if (! $device['current'])
                        <flux:button
                            type="button"
                            size="sm"
                            variant="danger"
                            wire:click="revokeSession('{{ $device['token'] }}')"
                            wire:confirm="{{ __('Revoke this browser session immediately?') }}"
                        >
                            {{ __('Revoke') }}
                        </flux:button>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-sm opacity-75">
                    {{ __('No active sessions match your filter.') }}
                </div>
            @endforelse
        </div>
    @endif
</x-admin.nav>
