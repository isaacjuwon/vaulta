<?php

declare(strict_types=1);

use App\Models\Authenticator;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Admin overview')] class extends Component {
    public function boot(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(boolean: $user === null || ! $user->is_admin || $user->is_blocked, code: 403);
    }

    /**
     * @return array{
     *     total_users: int,
     *     blocked_users: int,
     *     new_users_7d: int,
     *     total_authenticators: int,
     *     favorite_authenticators: int,
     *     active_sessions_1h: int|null
     * }
     */
    #[Computed]
    public function metrics(): array
    {
        $activeSessions = null;
        $sessionTable = config(key: 'session.table', default: 'sessions');
        if (config(key: 'session.driver') === 'database' && is_string($sessionTable) && Schema::hasTable(table: $sessionTable)) {
            $activeSessions = DB::table(table: $sessionTable)
                ->where(column: 'last_activity', operator: '>=', value: now()->subHour()->getTimestamp())
                ->count();
        }

        return [
            'total_users' => User::query()->count(),
            'blocked_users' => User::query()->blocked()->count(),
            'new_users_7d' => User::query()->createdSinceDays(days: 7)->count(),
            'total_authenticators' => Authenticator::query()->count(),
            'favorite_authenticators' => Authenticator::query()->favoritesOnly()->count(),
            'active_sessions_1h' => $activeSessions,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function recentUsers(): Collection
    {
        return User::query()
            ->withCount(relations: 'authenticators')
            ->latest()
            ->limit(value: 6)
            ->get();
    }
};
?>

<x-admin.nav
    :heading="__('Administration overview')"
    :subheading="__('Instance health, account totals, and encrypted authenticator activity across Vaulta.')"
>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-700 dark:bg-zinc-900/70">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider opacity-70">{{ __('Total users') }}</span>
                <flux:icon name="users" class="size-4 text-accent" />
            </div>
            <div class="mt-2 font-mono text-3xl font-semibold">{{ number_format($this->metrics['total_users']) }}</div>
            <div class="mt-1 text-xs opacity-70">
                {{ __(':count joined in the last 7 days', ['count' => $this->metrics['new_users_7d']]) }}
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-700 dark:bg-zinc-900/70">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider opacity-70">{{ __('Authenticators') }}</span>
                <flux:icon name="lock-closed" class="size-4 text-accent" />
            </div>
            <div class="mt-2 font-mono text-3xl font-semibold">{{ number_format($this->metrics['total_authenticators']) }}</div>
            <div class="mt-1 text-xs opacity-70">
                {{ __(':count starred as favorites', ['count' => $this->metrics['favorite_authenticators']]) }}
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-700 dark:bg-zinc-900/70">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider opacity-70">{{ __('Security status') }}</span>
                <flux:icon name="shield-check" class="size-4 text-accent" />
            </div>
            <div class="mt-2 font-mono text-3xl font-semibold">
                @if ($this->metrics['active_sessions_1h'] !== null)
                    {{ number_format($this->metrics['active_sessions_1h']) }}
                @else
                    {{ number_format($this->metrics['blocked_users']) }}
                @endif
            </div>
            <div class="mt-1 text-xs opacity-70">
                @if ($this->metrics['active_sessions_1h'] !== null)
                    {{ __('Active sessions (1h) · :blocked blocked users', ['blocked' => $this->metrics['blocked_users']]) }}
                @else
                    {{ __('Blocked accounts across instance') }}
                @endif
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">{{ __('Recently registered users') }}</flux:heading>
            <flux:button size="sm" variant="ghost" :href="route('admin.users')" wire:navigate.hover>
                {{ __('Manage all users') }}
            </flux:button>
        </div>

        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/70 dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/70">
            @forelse ($this->recentUsers as $recentUser)
                <div wire:key="recent-user-{{ $recentUser->id }}" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-semibold">{{ $recentUser->name }}</span>
                            @if ($recentUser->is_admin)
                                <flux:badge size="sm" color="amber">{{ __('Admin') }}</flux:badge>
                            @endif
                            @if ($recentUser->is_blocked)
                                <flux:badge size="sm" color="red">{{ __('Blocked') }}</flux:badge>
                            @endif
                        </div>
                        <div class="truncate text-xs opacity-70">
                            {{ $recentUser->email }} · {{ __('Joined :time', ['time' => $recentUser->created_at?->diffForHumans() ?? __('recently')]) }}
                        </div>
                    </div>

                    <div class="font-mono text-xs opacity-80">
                        {{ trans_choice(':count authenticator|:count authenticators', $recentUser->authenticators_count ?? 0, ['count' => $recentUser->authenticators_count ?? 0]) }}
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-sm opacity-75">
                    {{ __('No user accounts found.') }}
                </div>
            @endforelse
        </div>
    </div>
</x-admin.nav>
