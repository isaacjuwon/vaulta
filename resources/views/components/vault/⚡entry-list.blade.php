<?php

declare(strict_types=1);

use App\Actions\Entries\RemoveEntry;
use App\Actions\Entries\SetEntryColor;
use App\Actions\Entries\SetEntryFavorite;
use App\Actions\Entries\UpdateEntry;
use App\Actions\Totp\GenerateEntryCodes;
use App\Actions\Vault\ExportVault;
use App\Actions\Vault\ImportVault;
use App\Enums\TagColor;
use App\Models\Authenticator;
use App\Models\SiteSetting;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Transition;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component {
    use WithPagination;

    private const array PAGE_SIZES = [12, 24, 48, 96];

    #[Url(as: 'q', history: false)]
    public string $search = '';

    #[Url]
    public string $filter = 'All accounts';

    #[Url(as: 'color', history: false)]
    public string $colorFilter = '';

    public string $sort = 'issuer';

    public int $perPage = 12;

    public function updatedSearch(): void
    {
        $this->search = mb_substr(string: $this->search, start: 0, length: 100);
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedColorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array(needle: $this->perPage, haystack: self::PAGE_SIZES, strict: true)) {
            $this->perPage = 12;
        }

        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sort = match ($this->sort) {
            'issuer' => 'recent',
            'recent' => 'favorites',
            default => 'issuer',
        };

        $this->resetPage();
    }

    public function setColorFilter(string $color): void
    {
        $this->colorFilter = $this->colorFilter === $color ? '' : $color;
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array(needle: $filter, haystack: ['All accounts', 'Favorites'], strict: true)
            ? $filter
            : 'All accounts';
        $this->resetPage();
    }

    public function setSort(string $sort): void
    {
        $this->sort = match ($sort) {
            'recent' => 'recent',
            'favorites' => 'favorites',
            default => 'issuer',
        };

        $this->resetPage();
    }

    public function setPerPage(int $perPage): void
    {
        if (in_array(needle: $perPage, haystack: self::PAGE_SIZES, strict: true)) {
            $this->perPage = $perPage;
            $this->resetPage();
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->colorFilter = '';
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return filled($this->search)
            || $this->filter !== 'All accounts'
            || filled($this->colorFilter)
            || $this->sort !== 'issuer';
    }

    public function activeFilterCount(): int
    {
        $count = 0;

        if (filled($this->search)) {
            $count++;
        }

        if ($this->filter !== 'All accounts') {
            $count++;
        }

        if (filled($this->colorFilter)) {
            $count++;
        }

        if ($this->sort !== 'issuer') {
            $count++;
        }

        return $count;
    }

    #[On('entry-added')]
    public function handleEntryAdded(): void
    {
        unset($this->entries, $this->totalAccounts, $this->totalFavorites);
        $this->resetPage();
    }

    public function setFavorite(int $entryId, bool $favorite, SetEntryFavorite $setEntryFavorite): void
    {
        $entry = $this->ownedEntry(entryId: $entryId);
        $this->authorize(ability: 'update', arguments: $entry);

        $setEntryFavorite->handle(entry: $entry, favorite: $favorite);

        unset($this->entries, $this->totalFavorites);
        Flux::toast(
            variant: 'success',
            text: $favorite ? __('Added to favorites.') : __('Removed from favorites.'),
        );
    }

    public function setColor(int $entryId, ?string $color, SetEntryColor $setEntryColor): void
    {
        $entry = $this->ownedEntry(entryId: $entryId);
        $this->authorize(ability: 'update', arguments: $entry);

        $resolved = $color !== null && $color !== ''
            ? TagColor::tryFrom(value: $color)
            : TagColor::default();

        $setEntryColor->handle(entry: $entry, color: $resolved);

        unset($this->entries);
        Flux::toast(variant: 'success', text: __('Tag updated.'));
    }

    public function remove(int $entryId, RemoveEntry $removeEntry): void
    {
        $entry = $this->ownedEntry(entryId: $entryId);
        $this->authorize(ability: 'delete', arguments: $entry);

        $removeEntry->handle(entry: $entry);

        unset($this->entries, $this->totalAccounts, $this->totalFavorites);
        Flux::toast(variant: 'success', text: __('Account removed.'));
    }

    public bool $showEditModal = false;

    public ?int $editingEntryId = null;

    public string $editIssuer = '';

    public string $editAccount = '';

    public string $editSecret = '';

    public string $editColor = 'zinc';

    public function openEditModal(int $entryId): void
    {
        $entry = $this->ownedEntry(entryId: $entryId);
        $this->editingEntryId = $entry->id;
        $this->editIssuer = $entry->issuer;
        $this->editAccount = $entry->account;
        $this->editColor = $entry->tagColor()->value;
        $this->editSecret = '';
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingEntryId = null;
    }

    public function setEditColor(string $color): void
    {
        $this->editColor = $color;
    }

    public function saveEdit(UpdateEntry $updateEntry): void
    {
        if ($this->editingEntryId === null) {
            return;
        }

        $entry = $this->ownedEntry(entryId: $this->editingEntryId);
        $this->authorize(ability: 'update', arguments: $entry);

        $validated = $this->validate(rules: [
            'editIssuer' => ['required', 'string', 'max:100'],
            'editAccount' => ['required', 'string', 'max:180'],
            'editColor' => ['required', 'string', Rule::enum(type: TagColor::class)],
            'editSecret' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z2-7\s=-]*$/'],
        ]);

        $updateEntry->handle(
            entry: $entry,
            issuer: $validated['editIssuer'],
            account: $validated['editAccount'],
            color: TagColor::from(value: $validated['editColor']),
            secret: $validated['editSecret'] !== '' ? $validated['editSecret'] : null,
        );

        unset($this->entries);
        $this->showEditModal = false;
        $this->editingEntryId = null;
        Flux::toast(variant: 'success', text: __('Account details updated.'));
    }

    public bool $showExportModal = false;

    public string $exportPassword = '';

    public bool $allowPlainExport = true;

    public bool $showImportModal = false;

    public string $importPassword = '';

    public string $importContent = '';

    public function openExportModal(): void
    {
        $this->exportPassword = '';
        $this->allowPlainExport = (bool) (SiteSetting::get(key: 'allow_plain_export') ?? true);
        $this->showExportModal = true;
    }

    public function closeExportModal(): void
    {
        $this->showExportModal = false;
        $this->exportPassword = '';
    }

    public function downloadExport(ExportVault $exportVault): ?StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->allowPlainExport = (bool) (SiteSetting::get(key: 'allow_plain_export') ?? true);

        if (! $this->allowPlainExport && ($this->exportPassword === '' || trim(string: $this->exportPassword) === '')) {
            $this->addError(name: 'exportPassword', message: __('Plain text export is prohibited by instance policy. An encryption password is required.'));

            return null;
        }

        $password = $this->exportPassword !== '' ? $this->exportPassword : null;
        $json = $exportVault->handle(user: $user, password: $password);
        $filename = 'vaulta-backup-'.Carbon::now()->format(format: 'Y-m-d-His').'.json';

        $this->showExportModal = false;
        $this->exportPassword = '';

        return response()->streamDownload(
            callback: static function () use ($json): void {
                echo $json;
            },
            name: $filename,
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    public function openImportModal(): void
    {
        $this->importContent = '';
        $this->importPassword = '';
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importContent = '';
        $this->importPassword = '';
        $this->resetErrorBag();
    }

    public function executeImport(ImportVault $importVault): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (trim(string: $this->importContent) === '') {
            $this->addError(name: 'importContent', message: __('Please paste or choose a backup file.'));

            return;
        }

        try {
            $count = $importVault->handle(
                user: $user,
                fileContent: $this->importContent,
                password: $this->importPassword !== '' ? $this->importPassword : null,
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(name: $field === 'file' ? 'importContent' : $field, message: $message);
                }
            }

            return;
        }

        unset($this->entries, $this->totalAccounts, $this->totalFavorites);
        $this->showImportModal = false;
        $this->importContent = '';
        $this->importPassword = '';

        Flux::toast(variant: 'success', text: __(':count account(s) imported into your vault.', ['count' => $count]));
    }

    #[Transition(skip: true)]
    public function refreshCodes(): void
    {
        unset($this->entries);
    }

    /**
     * @return LengthAwarePaginator<int, Authenticator>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $query = Authenticator::query()
            ->ownedBy(user: $this->currentUser())
            ->matching(term: $this->search);

        if ($this->filter === 'Favorites') {
            $query->favoritesOnly();
        }

        if ($this->colorFilter !== '') {
            $tagColor = TagColor::tryFrom(value: $this->colorFilter);
            if ($tagColor !== null) {
                $query->coloured(color: $tagColor);
            }
        }

        return $query
            ->sortedBy(sort: $this->sort)
            ->paginate(perPage: $this->perPage);
    }

    #[Computed]
    public function totalAccounts(): int
    {
        return Authenticator::query()
            ->ownedBy(user: $this->currentUser())
            ->count();
    }

    #[Computed]
    public function totalFavorites(): int
    {
        return Authenticator::query()
            ->ownedBy(user: $this->currentUser())
            ->favoritesOnly()
            ->count();
    }

    private function ownedEntry(int $entryId): Authenticator
    {
        return Authenticator::query()
            ->ownedBy(user: $this->currentUser())
            ->findOrFail(id: $entryId);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
};
?>

<div
    x-data="{
        copiedId: null,
        copyStatus: '',
        copyFailedId: null,
        shortcutHandler: null,
        init() {
            this.shortcutHandler = (event) => {
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    this.$refs.vaultSearch?.focus();
                }
            };
            window.addEventListener('keydown', this.shortcutHandler);

            if (new URLSearchParams(window.location.search).has('focus')) {
                this.$nextTick(() => this.$refs.vaultSearch?.focus());
            }
        },
        destroy() {
            if (this.shortcutHandler) {
                window.removeEventListener('keydown', this.shortcutHandler);
            }
        },
        async copyCode(entryId, formattedCode) {
            const raw = String(formattedCode || '').replace(/\s+/g, '');
            this.copyFailedId = null;

            let ok = false;
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(raw);
                    ok = true;
                } catch (e) {
                    ok = false;
                }
            }

            if (!ok) {
                try {
                    const fallback = document.createElement('textarea');
                    fallback.value = raw;
                    fallback.setAttribute('readonly', '');
                    fallback.style.position = 'fixed';
                    fallback.style.opacity = '0';
                    document.body.appendChild(fallback);
                    fallback.select();
                    ok = document.execCommand('copy');
                    document.body.removeChild(fallback);
                } catch (e) {
                    ok = false;
                }
            }

            if (ok) {
                this.copiedId = entryId;
                this.copyStatus = 'Code copied to clipboard';
                setTimeout(() => {
                    if (this.copiedId === entryId) this.copiedId = null;
                }, 2000);
            } else {
                this.copyFailedId = entryId;
                this.copyStatus = 'Copy failed — select code manually';
            }
        },
        mobileFiltersOpen: false
    }"
    class="space-y-6"
>
    <p class="sr-only" role="status" aria-live="polite" x-text="copyStatus"></p>

    {{-- Stat summary bar --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between text-xs opacity-75">
                <span>{{ $filter === 'Favorites' ? __('Favorite accounts') : __('Saved accounts') }}</span>
                <flux:icon name="key" class="size-4 text-accent" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="font-serif text-2xl font-bold tabular-nums">
                    {{ str_pad((string) ($filter === 'Favorites' ? $this->entries->total() : $this->totalAccounts), 2, '0', STR_PAD_LEFT) }}
                </span>
                <span class="text-xs opacity-70">{{ $filter === 'Favorites' ? __('saved favorites') : __('ready to use') }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between text-xs opacity-75">
                <span>{{ __('Code window') }}</span>
                <flux:icon name="clock" class="size-4 text-accent" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="font-serif text-2xl font-bold tabular-nums">30s</span>
                <span class="text-xs opacity-70">{{ __('RFC 6238 SHA-1') }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between text-xs opacity-75">
                <span>{{ __('Vault protection') }}</span>
                <flux:icon name="lock-closed" class="size-4 text-accent" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="font-serif text-2xl font-bold">AES-256</span>
                <span class="text-xs opacity-70">{{ __('encrypted at rest') }}</span>
            </div>
        </div>
    </div>

    {{-- 1. Mobile Filter Bar (sm:hidden) --}}
    <div class="flex items-center gap-2 sm:hidden">
        {{-- Left slide trigger button --}}
        <button
            type="button"
            x-on:click="mobileFiltersOpen = true"
            aria-label="{{ __('Open filters') }}"
            class="relative inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:border-accent hover:text-accent dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
        >
            <flux:icon name="funnel" class="size-4 text-accent" />
            <span>{{ __('Filters') }}</span>
            @if ($this->activeFilterCount() > 0)
                <span class="flex size-5 items-center justify-center rounded-full bg-accent text-[10px] font-bold text-accent-foreground">
                    {{ $this->activeFilterCount() }}
                </span>
            @endif
        </button>

        {{-- Mobile Search Input --}}
        <div class="flex-1 min-w-0">
            <flux:input
                x-ref="vaultSearchMobile"
                data-vault-search
                wire:model.live.debounce.250ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search...')"
                aria-label="{{ __('Search accounts') }}"
                clearable
            />
        </div>

        {{-- Mobile quick sort --}}
        <flux:button
            type="button"
            variant="ghost"
            icon="arrows-up-down"
            wire:click="toggleSort"
            title="{{ match ($sort) { 'recent' => __('Recent'), 'favorites' => __('Favorites first'), default => __('A–Z') } }}"
        />
    </div>

    {{-- 2. Desktop Filter Bar (hidden sm:flex - NO filter icon, let filters spread) --}}
    <div class="hidden sm:flex sm:flex-col sm:gap-3 lg:flex-row lg:items-center lg:justify-between">
        {{-- Left: Filter Tabs & Color Tag Dots --}}
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-zinc-50/80 p-1 dark:border-zinc-700 dark:bg-zinc-900/80">
                <flux:button
                    type="button"
                    size="sm"
                    :variant="$filter === 'All accounts' ? 'primary' : 'ghost'"
                    wire:click="setFilter('All accounts')"
                    class="shrink-0 whitespace-nowrap"
                >
                    <flux:icon name="key" class="size-3.5 me-1" />
                    {{ __('All accounts') }} ({{ $this->totalAccounts }})
                </flux:button>

                <flux:button
                    type="button"
                    size="sm"
                    :variant="$filter === 'Favorites' ? 'primary' : 'ghost'"
                    wire:click="setFilter('Favorites')"
                    class="shrink-0 whitespace-nowrap"
                >
                    <flux:icon name="star" class="size-3.5 me-1" />
                    {{ __('Favorites') }} ({{ $this->totalFavorites }})
                </flux:button>
            </div>

            <div class="ms-1 flex items-center gap-1 shrink-0" role="group" aria-label="{{ __('Filter by colour tag') }}">
                @foreach (TagColor::filterableCases() as $tagOption)
                    <button
                        type="button"
                        wire:key="filter-tag-{{ $tagOption->value }}"
                        wire:click="setColorFilter('{{ $tagOption->value }}')"
                        aria-pressed="{{ $colorFilter === $tagOption->value ? 'true' : 'false' }}"
                        aria-label="{{ __('Filter by :color', ['color' => $tagOption->label()]) }}"
                        title="{{ __('Filter by :color (:shape)', ['color' => $tagOption->label(), 'shape' => $tagOption->shapeCue()]) }}"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 transition hover:border-accent aria-pressed:border-accent aria-pressed:ring-2 aria-pressed:ring-accent/40 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <span class="size-2.5 rounded-full {{ $tagOption->dotClass() }}" aria-hidden="true"></span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Right: Spread search, sort, export, import, per-page --}}
        <div class="flex items-center gap-2 flex-1 justify-end max-w-2xl">
            <div class="w-full max-w-xs">
                <flux:input
                    x-ref="vaultSearch"
                    data-vault-search
                    wire:model.live.debounce.250ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Find an account... (⌘K)')"
                    aria-label="{{ __('Search accounts') }}"
                />
            </div>

            <flux:button type="button" variant="ghost" icon="arrows-up-down" wire:click="toggleSort">
                {{ match ($sort) { 'recent' => __('Recent'), 'favorites' => __('Favorites first'), default => __('A–Z') } }}
            </flux:button>

            <flux:button type="button" size="sm" variant="ghost" icon="arrow-down-tray" wire:click="openExportModal" title="{{ __('Export backup (E)') }}">
                {{ __('Export') }}
            </flux:button>

            <flux:button type="button" size="sm" variant="ghost" icon="arrow-up-tray" wire:click="openImportModal" title="{{ __('Import accounts (I)') }}">
                {{ __('Import') }}
            </flux:button>

            <select
                wire:model.live="perPage"
                aria-label="{{ __('Items per page') }}"
                class="rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-2 text-xs font-medium dark:border-zinc-700 dark:bg-zinc-900"
            >
                @foreach ([12, 24, 48, 96] as $size)
                    <option value="{{ $size }}">{{ $size }} / {{ __('page') }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- 3. Active filter chips bar (appears on both mobile & desktop when active) --}}
    @if ($this->hasActiveFilters())
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50/60 p-2.5 text-xs dark:border-zinc-800 dark:bg-zinc-900/60">
            <span class="font-semibold opacity-70">{{ __('Active filters:') }}</span>

            @if (filled($search))
                <span class="inline-flex items-center gap-1 rounded-md bg-accent/15 px-2 py-0.5 text-accent font-medium">
                    <span>"{{ $search }}"</span>
                    <button type="button" wire:click="clearSearch" class="hover:opacity-75" aria-label="{{ __('Remove search filter') }}">×</button>
                </span>
            @endif

            @if ($filter !== 'All accounts')
                <span class="inline-flex items-center gap-1 rounded-md bg-accent/15 px-2 py-0.5 text-accent font-medium">
                    <span>{{ $filter }}</span>
                    <button type="button" wire:click="setFilter('All accounts')" class="hover:opacity-75" aria-label="{{ __('Remove category filter') }}">×</button>
                </span>
            @endif

            @if (filled($colorFilter))
                <span class="inline-flex items-center gap-1.5 rounded-md bg-accent/15 px-2 py-0.5 text-accent font-medium">
                    <span class="size-2 rounded-full {{ App\Enums\TagColor::tryFrom($colorFilter)?->dotClass() }}"></span>
                    <span>{{ App\Enums\TagColor::tryFrom($colorFilter)?->label() }}</span>
                    <button type="button" wire:click="setColorFilter('')" class="hover:opacity-75" aria-label="{{ __('Remove color filter') }}">×</button>
                </span>
            @endif

            @if ($sort !== 'issuer')
                <span class="inline-flex items-center gap-1 rounded-md bg-zinc-200/80 px-2 py-0.5 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 font-medium">
                    <span>{{ match ($sort) { 'recent' => __('Recent'), 'favorites' => __('Favorites first'), default => __('A–Z') } }}</span>
                    <button type="button" wire:click="setSort('issuer')" class="hover:opacity-75" aria-label="{{ __('Reset sort') }}">×</button>
                </span>
            @endif

            <button
                type="button"
                wire:click="clearFilters"
                class="ms-auto text-accent hover:underline font-semibold"
            >
                {{ __('Clear all') }}
            </button>
        </div>
    @endif

    {{-- 4. Mobile Left Slide-out Drawer (sm:hidden) --}}
    <div
        x-show="mobileFiltersOpen"
        x-cloak
        x-on:keydown.escape.window="mobileFiltersOpen = false"
        class="fixed inset-0 z-50 sm:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Filter authenticators') }}"
    >
        {{-- Backdrop --}}
        <div
            x-show="mobileFiltersOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="mobileFiltersOpen = false"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs"
            aria-hidden="true"
        ></div>

        {{-- Left Slide Panel --}}
        <div
            x-show="mobileFiltersOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 flex w-full max-w-xs flex-col border-e border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            {{-- Drawer Header --}}
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="funnel" class="size-4 text-accent" />
                    <span class="font-semibold text-sm">{{ __('Filters & Sorting') }}</span>
                    @if ($this->activeFilterCount() > 0)
                        <flux:badge size="xs" color="accent">{{ $this->activeFilterCount() }}</flux:badge>
                    @endif
                </div>
                <button
                    type="button"
                    x-on:click="mobileFiltersOpen = false"
                    class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 transition"
                    aria-label="{{ __('Close filters') }}"
                >
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </div>

            {{-- Drawer Content (Scrollable) --}}
            <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5 text-xs">
                {{-- Search input --}}
                <div class="space-y-1.5">
                    <flux:label>{{ __('Search accounts') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.250ms="search"
                        icon="magnifying-glass"
                        :placeholder="__('Search issuer, account...')"
                        clearable
                    />
                </div>

                {{-- Filter by category --}}
                <div class="space-y-2">
                    <flux:label>{{ __('Category') }}</flux:label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="setFilter('All accounts')"
                            @class([
                                'flex items-center justify-center gap-1.5 rounded-xl border p-2.5 font-medium transition',
                                'border-accent bg-accent/10 text-accent font-semibold' => $filter === 'All accounts',
                                'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => $filter !== 'All accounts',
                            ])
                        >
                            <flux:icon name="key" class="size-3.5" />
                            <span>{{ __('All') }} ({{ $this->totalAccounts }})</span>
                        </button>
                        <button
                            type="button"
                            wire:click="setFilter('Favorites')"
                            @class([
                                'flex items-center justify-center gap-1.5 rounded-xl border p-2.5 font-medium transition',
                                'border-accent bg-accent/10 text-accent font-semibold' => $filter === 'Favorites',
                                'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => $filter !== 'Favorites',
                            ])
                        >
                            <flux:icon name="star" class="size-3.5" />
                            <span>{{ __('Favorites') }} ({{ $this->totalFavorites }})</span>
                        </button>
                    </div>
                </div>

                {{-- Filter by colour tag --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('Colour tags') }}</flux:label>
                        @if (filled($colorFilter))
                            <button
                                type="button"
                                wire:click="setColorFilter('')"
                                class="text-[11px] text-accent hover:underline"
                            >
                                {{ __('Clear colour') }}
                            </button>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (TagColor::filterableCases() as $tagOption)
                            <button
                                type="button"
                                wire:click="setColorFilter('{{ $tagOption->value }}')"
                                @class([
                                    'flex items-center gap-2 rounded-xl border p-2 text-left transition',
                                    'border-accent bg-accent/10 font-semibold text-accent ring-1 ring-accent' => $colorFilter === $tagOption->value,
                                    'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => $colorFilter !== $tagOption->value,
                                ])
                            >
                                <span class="size-3 rounded-full shrink-0 {{ $tagOption->dotClass() }}"></span>
                                <span class="truncate">{{ $tagOption->label() }}</span>
                                <span class="ms-auto opacity-50 font-mono text-[10px]">{{ $tagOption->shapeCue() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Sort options --}}
                <div class="space-y-2">
                    <flux:label>{{ __('Sort order') }}</flux:label>
                    <div class="space-y-1.5">
                        @foreach ([
                            'issuer' => ['label' => __('Alphabetical (A–Z)'), 'icon' => 'bars-arrow-down'],
                            'recent' => ['label' => __('Recently added'), 'icon' => 'clock'],
                            'favorites' => ['label' => __('Favorites first'), 'icon' => 'star'],
                        ] as $sortKey => $sortOption)
                            <button
                                type="button"
                                wire:click="setSort('{{ $sortKey }}')"
                                @class([
                                    'flex w-full items-center justify-between rounded-xl border p-2.5 text-left transition',
                                    'border-accent bg-accent/10 font-semibold text-accent' => $sort === $sortKey,
                                    'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => $sort !== $sortKey,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <flux:icon name="{{ $sortOption['icon'] }}" class="size-3.5" />
                                    <span>{{ $sortOption['label'] }}</span>
                                </div>
                                @if ($sort === $sortKey)
                                    <flux:icon name="check" class="size-4 text-accent" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Per page --}}
                <div class="space-y-1.5">
                    <flux:label>{{ __('Items per page') }}</flux:label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ([12, 24, 48, 96] as $size)
                            <button
                                type="button"
                                wire:click="setPerPage({{ $size }})"
                                @class([
                                    'rounded-lg border p-2 text-center font-medium transition',
                                    'border-accent bg-accent/10 font-semibold text-accent' => $perPage === $size,
                                    'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60' => $perPage !== $size,
                                ])
                            >
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Backup actions --}}
                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-800 space-y-2">
                    <flux:label>{{ __('Backup & Portability') }}</flux:label>
                    <div class="grid grid-cols-2 gap-2">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="arrow-down-tray"
                            wire:click="openExportModal"
                            class="w-full justify-center"
                        >
                            {{ __('Export backup') }}
                        </flux:button>
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="arrow-up-tray"
                            wire:click="openImportModal"
                            class="w-full justify-center"
                        >
                            {{ __('Import backup') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Drawer Footer Actions --}}
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800 flex items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-900/90">
                <flux:button
                    type="button"
                    variant="ghost"
                    size="sm"
                    wire:click="clearFilters"
                    :disabled="! $this->hasActiveFilters()"
                >
                    {{ __('Reset all') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="primary"
                    size="sm"
                    x-on:click="mobileFiltersOpen = false"
                    class="flex-1"
                >
                    {{ __('View :count accounts', ['count' => $this->entries->total()]) }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Isolated Codes Island with server-authoritative countdown & boundary refresh --}}
    @island(name: 'codes', always: true)
        @php
            $codeGenerator = app(GenerateEntryCodes::class);
            $serverNow = time();
            $codes = $codeGenerator->handle(entries: $this->entries->items(), at: $serverNow);
            $initialRemaining = $codeGenerator->remainingSeconds(at: $serverNow);
        @endphp

        <div
            x-data="{
                period: 30,
                offset: {{ $serverNow }} - Math.floor(Date.now() / 1000),
                remaining: {{ $initialRemaining }},
                lastStep: Math.floor({{ $serverNow }} / 30),
                refreshing: false,
                timerId: null,
                visibilityListener: null,
                announceText: '',
                init() {
                    this.tick();
                    this.timerId = setInterval(() => this.tick(), 500);
                    this.visibilityListener = () => {
                        if (document.visibilityState === 'visible') {
                            this.triggerIslandRefresh();
                        }
                    };
                    document.addEventListener('visibilitychange', this.visibilityListener);
                },
                destroy() {
                    if (this.timerId) clearInterval(this.timerId);
                    if (this.visibilityListener) document.removeEventListener('visibilitychange', this.visibilityListener);
                },
                handleWindowKeydown(e) {
                    if (e.target.matches('input, textarea, select, [contenteditable]')) return;
                    if (e.key === 'n' || e.key === 'N') {
                        $dispatch('open-add-entry-modal');
                        e.preventDefault();
                    } else if (e.key === 'e' || e.key === 'E') {
                        $wire.openExportModal();
                        e.preventDefault();
                    } else if (e.key === 'i' || e.key === 'I') {
                        $wire.openImportModal();
                        e.preventDefault();
                    } else if (e.key >= '1' && e.key <= '9') {
                        const index = parseInt(e.key, 10) - 1;
                        const buttons = document.querySelectorAll('[data-copy-trigger]');
                        if (buttons[index]) {
                            buttons[index].click();
                            e.preventDefault();
                        }
                    }
                },
                tick() {
                    const alignedNow = Math.floor(Date.now() / 1000) + this.offset;
                    const rem = ((alignedNow % this.period) + this.period) % this.period;
                    this.remaining = this.period - rem;
                    const currentStep = Math.floor(alignedNow / this.period);
                    if (currentStep > this.lastStep && !this.refreshing) {
                        this.lastStep = currentStep;
                        this.triggerIslandRefresh();
                    }
                },
                async triggerIslandRefresh() {
                    if (this.refreshing) return;
                    this.refreshing = true;
                    try {
                        if (typeof $wire.$island === 'function') {
                            await $wire.$island('codes').refreshCodes();
                        } else {
                            await $wire.refreshCodes();
                        }
                        this.announceText = 'Verification codes refreshed';
                    } finally {
                        this.refreshing = false;
                    }
                }
            }"
            data-server-time="{{ $serverNow }}"
            data-period="30"
            @keydown.window="handleWindowKeydown($event)"
        >
            <span class="sr-only" aria-live="polite" x-text="announceText"></span>

            @if ($this->entries->isEmpty())
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 shadow-xs dark:border-zinc-700 dark:bg-zinc-900/80">
                    @if ($filter === 'Favorites' && ($search !== '' || $colorFilter !== ''))
                        <flux:heading size="lg">{{ __('No favorites match that search.') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Try another query or clear your active filters to view your favorite accounts.') }}</flux:text>
                        <div class="mt-4">
                            <flux:button type="button" variant="outline" wire:click="clearFilters">
                                {{ __('Clear filters') }}
                            </flux:button>
                        </div>
                    @elseif ($filter === 'Favorites')
                        <flux:heading size="lg">{{ __('No favorites yet.') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Star any authenticator card to pin it to your favorites view.') }}</flux:text>
                        <div class="mt-4">
                            <flux:button type="button" variant="outline" wire:click="setFilter('All accounts')">
                                {{ __('Browse all accounts') }}
                            </flux:button>
                        </div>
                    @elseif ($this->totalAccounts > 0)
                        <flux:heading size="lg">{{ __('No accounts match that search.') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Try searching by service name or account email, or clear your search.') }}</flux:text>
                        <div class="mt-4">
                            <flux:button type="button" variant="outline" wire:click="clearFilters">
                                {{ __('Clear search') }}
                            </flux:button>
                        </div>
                    @else
                        <flux:heading size="lg">{{ __('Keep your sign-in codes close.') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Add your first authenticator setup key to generate RFC 6238 six-digit codes.') }}</flux:text>
                        <div class="mt-4">
                            <flux:button type="button" variant="primary" icon="plus" wire:click="$dispatch('open-add-entry-modal')">
                                {{ __('Add your first account') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @else
                <ul wire:transition="entry-list" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->entries as $entry)
                        @php
                            $totpCode = $codes->get($entry->id);
                            $formattedCode = $totpCode?->formatted() ?? '--- ---';
                            $tagColor = $entry->resolvedColor();
                        @endphp
                        <li
                            wire:key="entry-{{ $entry->id }}"
                            wire:transition
                            data-test="entry-card-{{ $entry->id }}"
                            class="authenticator-card group flex flex-col justify-between rounded-xl border border-zinc-200 bg-zinc-50/90 p-4 shadow-xs transition hover:border-accent/60 dark:border-zinc-700 dark:bg-zinc-900/90"
                        >
                            <div class="flex items-start gap-3">
                                <x-ui.issuer-icon :issuer="$entry->issuer" :initials="$entry->issuerInitials()" />

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="truncate text-sm font-bold">{{ $entry->issuer }}</h3>
                                        <x-ui.tag-dot :color="$tagColor" />
                                    </div>
                                    <p class="mt-0.5 truncate text-xs opacity-75">{{ $entry->account }}</p>
                                </div>

                                <div class="flex items-center gap-0.5">
                                    <button
                                        type="button"
                                        wire:click="setFavorite({{ $entry->id }}, {{ $entry->is_favorite ? 'false' : 'true' }})"
                                        aria-pressed="{{ $entry->is_favorite ? 'true' : 'false' }}"
                                        aria-label="{{ $entry->is_favorite ? __('Remove from favorites') : __('Add to favorites') }}"
                                        title="{{ $entry->is_favorite ? __('Remove from favorites') : __('Add to favorites') }}"
                                        class="rounded-lg p-1.5 transition hover:bg-zinc-200/70 focus-visible:outline-2 focus-visible:outline-accent dark:hover:bg-zinc-800 {{ $entry->is_favorite ? 'text-accent' : 'opacity-60 hover:opacity-100' }}"
                                    >
                                        <flux:icon name="star" :variant="$entry->is_favorite ? 'solid' : 'outline'" class="size-4" />
                                    </button>

                                    <flux:dropdown position="bottom" align="end">
                                        <button
                                            type="button"
                                            aria-label="{{ __('Colour tag options for :issuer', ['issuer' => $entry->issuer]) }}"
                                            class="rounded-lg p-1.5 opacity-60 transition hover:bg-zinc-200/70 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-accent dark:hover:bg-zinc-800"
                                        >
                                            <flux:icon name="swatch" class="size-4" />
                                        </button>

                                        <flux:menu>
                                            @foreach (TagColor::cases() as $colorOption)
                                                <flux:menu.item
                                                    wire:key="entry-{{ $entry->id }}-color-{{ $colorOption->value }}"
                                                    wire:click="setColor({{ $entry->id }}, '{{ $colorOption->value }}')"
                                                >
                                                    <span class="me-2 inline-block size-2.5 rounded-full {{ $colorOption->dotClass() }}" aria-hidden="true"></span>
                                                    {{ $colorOption->label() }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>

                                    <button
                                        type="button"
                                        wire:click="openEditModal({{ $entry->id }})"
                                        aria-label="{{ __('Edit :issuer', ['issuer' => $entry->issuer]) }}"
                                        title="{{ __('Edit account') }}"
                                        class="rounded-lg p-1.5 opacity-60 transition hover:bg-zinc-200/70 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-accent dark:hover:bg-zinc-800"
                                    >
                                        <flux:icon name="pencil-square" class="size-4" />
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="remove({{ $entry->id }})"
                                        wire:confirm="{{ __('Remove this account from your vault?') }}"
                                        aria-label="{{ __('Remove :issuer', ['issuer' => $entry->issuer]) }}"
                                        title="{{ __('Remove account') }}"
                                        class="rounded-lg p-1.5 opacity-60 transition hover:bg-red-500/10 hover:text-red-600 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-accent dark:hover:text-red-400"
                                    >
                                        <flux:icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-700/80">
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        data-copy-trigger="{{ $loop->index }}"
                                        x-on:click="copyCode({{ $entry->id }}, '{{ $formattedCode }}')"
                                        class="group/code inline-flex items-center gap-2 rounded-lg px-1 py-0.5 text-start focus-visible:outline-2 focus-visible:outline-accent"
                                        aria-label="{{ __('Copy verification code for :issuer', ['issuer' => $entry->issuer]) }}"
                                    >
                                        <span class="font-mono text-2xl font-bold tracking-widest tabular-nums">
                                            {{ $formattedCode }}
                                        </span>
                                        <flux:icon name="clipboard-document" class="size-4 opacity-60 transition group-hover/code:opacity-100" />
                                    </button>

                                    <span
                                        x-show="copiedId === {{ $entry->id }}"
                                        x-cloak
                                        class="rounded bg-accent px-2 py-0.5 text-[11px] font-semibold text-accent-foreground"
                                    >
                                        {{ __('Copied') }}
                                    </span>

                                    <span
                                        x-show="copyFailedId === {{ $entry->id }}"
                                        x-cloak
                                        class="text-[11px] font-semibold text-red-600 dark:text-red-400"
                                    >
                                        {{ __('Copy failed') }}
                                    </span>
                                </div>

                                @if ($totpCode?->nextValue)
                                    <div
                                        x-show="remaining <= 5"
                                        x-cloak
                                        class="mt-1.5 flex items-center justify-between text-[11px] text-amber-600 dark:text-amber-400"
                                    >
                                        <span class="inline-flex items-center gap-1 font-medium">
                                            <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            <span>{{ __('Expiring soon') }}</span>
                                        </span>
                                        <button
                                            type="button"
                                            x-on:click.stop="copyCode({{ $entry->id }}, '{{ $totpCode->formattedNext() }}')"
                                            class="font-mono font-bold hover:underline"
                                            title="{{ __('Copy upcoming code') }}"
                                        >
                                            {{ __('Next: :code', ['code' => $totpCode->formattedNext()]) }}
                                        </button>
                                    </div>
                                @endif

                                <div class="mt-3">
                                    <x-ui.countdown :remaining-seconds="$initialRemaining" :period="30" />
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($this->entries->hasPages())
                    <div class="mt-6">
                        {{ $this->entries->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endisland

    {{-- Edit Account Modal --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-lg">
        <form wire:submit="saveEdit" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Edit account') }}</flux:heading>
                <flux:subheading>{{ __('Update service name, account label, or colour tag.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="editIssuer"
                :label="__('Service name')"
                placeholder="e.g. GitHub"
                required
            />

            <flux:input
                wire:model="editAccount"
                :label="__('Account label')"
                placeholder="you@example.com"
                required
            />

            <flux:field>
                <flux:label>{{ __('Update secret key (optional)') }}</flux:label>
                <flux:input
                    wire:model="editSecret"
                    autocomplete="off"
                    placeholder="{{ __('Leave blank to keep current encrypted secret') }}"
                    class="font-mono tracking-wider"
                />
                <flux:error name="editSecret" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Colour tag') }}</flux:label>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Colour tag') }}">
                    @foreach (TagColor::cases() as $tagCase)
                        <button
                            type="button"
                            wire:key="edit-tag-{{ $tagCase->value }}"
                            role="radio"
                            aria-checked="{{ $editColor === $tagCase->value ? 'true' : 'false' }}"
                            wire:click="setEditColor('{{ $tagCase->value }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition aria-checked:border-accent aria-checked:ring-2 aria-checked:ring-accent/40 {{ $tagCase->ringClass() }}"
                        >
                            <span class="size-2.5 rounded-full {{ $tagCase->dotClass() }}" aria-hidden="true"></span>
                            <span>{{ $tagCase->label() }}</span>
                        </button>
                    @endforeach
                </div>
            </flux:field>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="closeEditModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Export Vault Modal --}}
    <flux:modal wire:model="showExportModal" class="w-full max-w-md">
        <form wire:submit="downloadExport" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Export vault backup') }}</flux:heading>
                <flux:subheading>{{ __('Download a portable backup of your authenticator accounts.') }}</flux:subheading>
            </div>

            <flux:field>
                @if ($allowPlainExport)
                    <flux:label>{{ __('Encryption password (optional)') }}</flux:label>
                    <flux:input
                        type="password"
                        wire:model="exportPassword"
                        placeholder="{{ __('Leave blank for plaintext JSON') }}"
                    />
                    <flux:description>{{ __('If provided, your export will be encrypted with AES-256-GCM + PBKDF2.') }}</flux:description>
                @else
                    <flux:label>{{ __('Encryption password (required)') }}</flux:label>
                    <flux:input
                        type="password"
                        wire:model="exportPassword"
                        required
                        placeholder="{{ __('Enter an encryption password') }}"
                    />
                    <flux:description class="text-amber-600 dark:text-amber-400 font-medium">{{ __('Plaintext export is disabled by administrator policy. All backups must be encrypted.') }}</flux:description>
                @endif
                <flux:error name="exportPassword" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="closeExportModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="arrow-down-tray">
                    {{ __('Download backup') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Import Vault Modal --}}
    <flux:modal wire:model="showImportModal" class="w-full max-w-lg">
        <div
            x-data="{
                handleFile(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        $wire.set('importContent', e.target.result);
                    };
                    reader.readAsText(file);
                }
            }"
            class="space-y-4"
        >
            <div>
                <flux:heading size="lg">{{ __('Import authenticators') }}</flux:heading>
                <flux:subheading>{{ __('Supports Vaulta, Aegis, 2FAS, Bitwarden, or OTPAuth backup files.') }}</flux:subheading>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-zinc-100/60 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                <label for="vault-import-file" class="block text-xs font-semibold">
                    {{ __('Select backup file (.json, .txt)') }}
                </label>
                <input
                    id="vault-import-file"
                    type="file"
                    accept=".json,.txt,text/plain,application/json"
                    x-on:change="handleFile($event)"
                    class="mt-2 block w-full cursor-pointer rounded-lg border border-zinc-300 bg-zinc-50 text-xs file:me-3 file:cursor-pointer file:border-0 file:bg-accent file:px-3 file:py-2 file:text-xs file:font-semibold file:text-accent-foreground dark:border-zinc-700 dark:bg-zinc-950"
                />
            </div>

            <flux:field>
                <flux:label>{{ __('Or paste JSON / OTPAuth links directly') }}</flux:label>
                <flux:textarea
                    wire:model="importContent"
                    rows="3"
                    placeholder="{{ __('Paste raw JSON or otpauth:// links here...') }}"
                />
                <flux:error name="importContent" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Backup password (if encrypted)') }}</flux:label>
                <flux:input
                    type="password"
                    wire:model="importPassword"
                    placeholder="{{ __('Password used when creating the backup') }}"
                />
                <flux:error name="password" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="closeImportModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="primary" icon="arrow-up-tray" wire:click="executeImport">
                    {{ __('Import accounts') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
