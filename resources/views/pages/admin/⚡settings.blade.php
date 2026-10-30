<?php

declare(strict_types=1);

use App\Actions\Admin\ToggleMaintenanceMode;
use App\Actions\Admin\UpdateSiteSettings;
use App\Enums\Palette;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\SiteSetting;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] #[Title('Site settings')] class extends Component {
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $tab = 'general';

    public string $siteName = '';

    public string $supportEmail = '';

    public string $issuerName = '';

    public string $registrationMode = 'open';

    public string $defaultTheme = 'ferrite';

    public string $siteDescription = '';

    public bool $allowPlainExport = true;

    public bool $requireEmailVerification = true;

    public int $sessionLifetime = 120;

    public int $maxLoginAttempts = 5;

    public ?string $siteLogo = null;

    public ?string $siteFavicon = null;

    public string $footerCopyright = '';

    public string $footerTagline = '';

    public string $socialGithub = '';

    public string $socialTwitter = '';

    public string $socialDiscord = '';

    public string $socialBluesky = '';

    public string $newQuestion = '';

    public string $newAnswer = '';

    public bool $showAddFaqModal = false;

    public ?int $editingFaqId = null;

    public string $editingQuestion = '';

    public string $editingAnswer = '';

    public bool $editingIsPublished = true;

    public bool $showEditFaqModal = false;

    /**
     * @var mixed
     */
    public $logoFile = null;

    /**
     * @var mixed
     */
    public $faviconFile = null;

    public bool $maintenanceEnabled = false;

    public string $maintenanceSecret = '';

    public string $maintenanceMessage = '';

    public int $maintenanceRetry = 60;

    public bool $showMaintenanceModal = false;

    public function boot(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(boolean: $user === null || ! $user->is_admin || $user->is_blocked, code: 403);
    }

    public function mount(): void
    {
        if (! in_array(needle: $this->tab, haystack: ['general', 'security', 'branding', 'footer', 'faqs', 'maintenance', 'inquiries'], strict: true)) {
            $this->tab = 'general';
        }

        $this->siteName = (string) (SiteSetting::get(key: 'site_name') ?? config(key: 'app.name', default: 'Vaulta'));
        $this->supportEmail = (string) (SiteSetting::get(key: 'support_email') ?? 'admin@vaulta.local');
        $this->issuerName = (string) (SiteSetting::get(key: 'issuer_name') ?? config(key: 'vaulta.issuer', default: 'Vaulta'));
        $this->registrationMode = (string) (SiteSetting::get(key: 'registration_mode') ?? 'open');
        $this->defaultTheme = (string) (SiteSetting::get(key: 'default_theme') ?? 'ferrite');
        $this->siteDescription = (string) (SiteSetting::get(key: 'site_description') ?? 'Enterprise self-hosted two-factor authenticator with zero telemetry.');
        $this->allowPlainExport = (bool) (SiteSetting::get(key: 'allow_plain_export') ?? true);
        $this->requireEmailVerification = SiteSetting::requiresEmailVerification();
        $this->sessionLifetime = (int) (SiteSetting::get(key: 'session_lifetime') ?? 120);
        $this->maxLoginAttempts = (int) (SiteSetting::get(key: 'max_login_attempts') ?? 5);

        $this->siteLogo = SiteSetting::logoUrl();
        $this->siteFavicon = SiteSetting::faviconUrl();
        $this->footerCopyright = (string) (SiteSetting::get(key: 'footer_copyright') ?? '');
        $this->footerTagline = (string) (SiteSetting::get(key: 'footer_tagline') ?? '');
        $this->socialGithub = (string) (SiteSetting::get(key: 'social_github') ?? '');
        $this->socialTwitter = (string) (SiteSetting::get(key: 'social_twitter') ?? '');
        $this->socialDiscord = (string) (SiteSetting::get(key: 'social_discord') ?? '');
        $this->socialBluesky = (string) (SiteSetting::get(key: 'social_bluesky') ?? '');

        $this->maintenanceEnabled = app()->isDownForMaintenance();
        $this->maintenanceSecret = (string) (SiteSetting::get(key: 'maintenance_secret') ?? 'vaulta-bypass');
        $this->maintenanceMessage = (string) (SiteSetting::get(key: 'maintenance_message') ?? 'Vaulta is currently undergoing scheduled maintenance.');
        $this->maintenanceRetry = (int) (SiteSetting::get(key: 'maintenance_retry') ?? 60);
    }

    public function setTab(string $tab): void
    {
        if (in_array(needle: $tab, haystack: ['general', 'security', 'branding', 'footer', 'faqs', 'maintenance', 'inquiries'], strict: true)) {
            $this->tab = $tab;
        }
    }

    public function saveSettings(UpdateSiteSettings $updateSiteSettings): void
    {
        if ($this->logoFile !== null && method_exists($this->logoFile, 'getClientOriginalExtension')) {
            $this->validate(['logoFile' => ['image', 'max:2048']]);
            $dir = public_path(path: 'uploads/branding');
            File::ensureDirectoryExists(path: $dir);
            $filename = 'logo_'.time().'.'.$this->logoFile->getClientOriginalExtension();
            $this->logoFile->move(directory: $dir, name: $filename);
            $this->siteLogo = '/uploads/branding/'.$filename;
            $this->logoFile = null;
        }

        if ($this->faviconFile !== null && method_exists($this->faviconFile, 'getClientOriginalExtension')) {
            $this->validate(['faviconFile' => ['file', 'mimes:png,ico,svg,webp', 'max:1024']]);
            $dir = public_path(path: 'uploads/branding');
            File::ensureDirectoryExists(path: $dir);
            $filename = 'favicon_'.time().'.'.$this->faviconFile->getClientOriginalExtension();
            $this->faviconFile->move(directory: $dir, name: $filename);
            $this->siteFavicon = '/uploads/branding/'.$filename;
            $this->faviconFile = null;
        }

        $updateSiteSettings->handle(data: [
            'site_name' => $this->siteName,
            'support_email' => $this->supportEmail,
            'issuer_name' => $this->issuerName,
            'registration_mode' => $this->registrationMode,
            'default_theme' => $this->defaultTheme,
            'allow_plain_export' => $this->allowPlainExport,
            'require_email_verification' => $this->requireEmailVerification,
            'site_logo' => $this->siteLogo,
            'site_favicon' => $this->siteFavicon,
            'footer_copyright' => $this->footerCopyright,
            'footer_tagline' => $this->footerTagline,
            'social_github' => $this->socialGithub,
            'social_twitter' => $this->socialTwitter,
            'social_discord' => $this->socialDiscord,
            'social_bluesky' => $this->socialBluesky,
            'site_description' => $this->siteDescription,
            'session_lifetime' => $this->sessionLifetime,
            'max_login_attempts' => $this->maxLoginAttempts,
        ]);

        Flux::toast(variant: 'success', text: __('Site settings updated successfully.'));
    }

    public function addFaq(): void
    {
        $this->validate([
            'newQuestion' => ['required', 'string', 'max:255'],
            'newAnswer' => ['required', 'string', 'max:2000'],
        ]);

        $maxOrder = (int) (Faq::query()->max(column: 'sort_order') ?? 0);

        Faq::query()->create([
            'question' => trim(string: $this->newQuestion),
            'answer' => trim(string: $this->newAnswer),
            'sort_order' => $maxOrder + 1,
            'is_published' => true,
        ]);

        $this->newQuestion = '';
        $this->newAnswer = '';
        $this->showAddFaqModal = false;
        unset($this->faqs);

        Flux::toast(variant: 'success', text: __('FAQ question added.'));
    }

    public function editFaq(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);
        $this->editingFaqId = $faq->id;
        $this->editingQuestion = $faq->question;
        $this->editingAnswer = $faq->answer;
        $this->editingIsPublished = (bool) $faq->is_published;
        $this->showEditFaqModal = true;
    }

    public function updateFaq(): void
    {
        if ($this->editingFaqId === null) {
            return;
        }

        $this->validate([
            'editingQuestion' => ['required', 'string', 'max:255'],
            'editingAnswer' => ['required', 'string', 'max:2000'],
        ]);

        $faq = Faq::query()->findOrFail($this->editingFaqId);
        $faq->update([
            'question' => trim(string: $this->editingQuestion),
            'answer' => trim(string: $this->editingAnswer),
            'is_published' => $this->editingIsPublished,
        ]);

        $this->showEditFaqModal = false;
        $this->editingFaqId = null;
        unset($this->faqs);

        Flux::toast(variant: 'success', text: __('FAQ updated successfully.'));
    }

    public function toggleFaqPublished(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);
        $faq->update(['is_published' => ! $faq->is_published]);
        unset($this->faqs);

        Flux::toast(variant: 'success', text: $faq->is_published ? __('FAQ published.') : __('FAQ draft saved.'));
    }

    public function deleteFaq(int $id): void
    {
        $faq = Faq::query()->find($id);
        if (! $faq) {
            $faq = Faq::query()->ordered()->skip($id)->first();
        }

        $faq?->delete();
        unset($this->faqs);

        Flux::toast(variant: 'success', text: __('FAQ removed.'));
    }

    public function moveFaqUp(int $id): void
    {
        /** @var Collection<int, Faq> $all */
        $all = Faq::query()->ordered()->get();
        $index = $all->search(fn (Faq $f) => $f->id === $id);
        if ($index === false && isset($all[$id])) {
            $index = $id;
        }

        if ($index !== false && is_int($index) && $index > 0) {
            $current = $all->get($index);
            $prev = $all->get($index - 1);
            if ($current && $prev) {
                $temp = $current->sort_order;
                $current->sort_order = $prev->sort_order;
                $prev->sort_order = $temp;
                if ($current->sort_order === $prev->sort_order) {
                    $current->sort_order = $index;
                    $prev->sort_order = $index + 1;
                }
                $current->save();
                $prev->save();
                unset($this->faqs);
            }
        }
    }

    public function moveFaqDown(int $id): void
    {
        /** @var Collection<int, Faq> $all */
        $all = Faq::query()->ordered()->get();
        $index = $all->search(fn (Faq $f) => $f->id === $id);
        if ($index === false && isset($all[$id])) {
            $index = $id;
        }

        if ($index !== false && is_int($index) && $index < $all->count() - 1) {
            $current = $all->get($index);
            $next = $all->get($index + 1);
            if ($current && $next) {
                $temp = $current->sort_order;
                $current->sort_order = $next->sort_order;
                $next->sort_order = $temp;
                if ($current->sort_order === $next->sort_order) {
                    $current->sort_order = $index + 2;
                    $next->sort_order = $index + 1;
                }
                $current->save();
                $next->save();
                unset($this->faqs);
            }
        }
    }

    public function resetDefaultFaqs(): void
    {
        Faq::query()->truncate();

        foreach (Faq::defaults() as $default) {
            Faq::query()->create($default);
        }

        unset($this->faqs);
        Flux::toast(variant: 'success', text: __('Default FAQs restored.'));
    }

    /**
     * @return Collection<int, Faq>
     */
    #[Computed]
    public function faqs(): Collection
    {
        return Faq::query()->ordered()->get();
    }

    public function removeLogo(): void
    {
        $this->siteLogo = null;
        $this->logoFile = null;
        Flux::toast(variant: 'success', text: __('Custom logo reset to default.'));
    }

    public function removeFavicon(): void
    {
        $this->siteFavicon = null;
        $this->faviconFile = null;
        Flux::toast(variant: 'success', text: __('Custom favicon reset to default.'));
    }

    public function toggleMaintenance(ToggleMaintenanceMode $toggleMaintenanceMode): void
    {
        $nextState = ! $this->maintenanceEnabled;

        $toggleMaintenanceMode->handle(
            enabled: $nextState,
            secret: $this->maintenanceSecret,
            message: $this->maintenanceMessage,
            retry: $this->maintenanceRetry,
        );

        $this->maintenanceEnabled = $nextState;
        $this->showMaintenanceModal = false;

        Flux::toast(
            variant: $nextState ? 'warning' : 'success',
            text: $nextState ? __('Maintenance mode enabled.') : __('Site is back online.'),
        );
    }

    public function deleteMessage(int $id): void
    {
        ContactMessage::query()->where(column: 'id', operator: '=', value: $id)->delete();
        unset($this->contactMessages);
        Flux::toast(variant: 'success', text: __('Inquiry deleted.'));
    }

    /**
     * @return Collection<int, ContactMessage>
     */
    #[Computed]
    public function contactMessages(): Collection
    {
        return ContactMessage::query()
            ->latest()
            ->limit(value: 20)
            ->get();
    }
};
?>

<x-admin.nav
    :heading="__('Site settings & maintenance')"
    :subheading="__('Manage general instance configuration, security policies, branding, FAQs, and system state.')"
>
    {{-- Tabs Navbar --}}
    <div
        class="fluid-tabs -mx-4 w-[calc(100%+2rem)] px-4 pb-2 sm:mx-0 sm:w-auto sm:px-0"
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
        <flux:navbar class="-mb-px flex-nowrap shrink-0 gap-1 border-b border-zinc-200 pb-2 dark:border-zinc-800">
            <flux:navbar.item
                icon="cog-6-tooth"
                wire:click="setTab('general')"
                :current="$tab === 'general'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('General') }}
            </flux:navbar.item>

            <flux:navbar.item
                icon="shield-check"
                wire:click="setTab('security')"
                :current="$tab === 'security'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('Security & Auth') }}
            </flux:navbar.item>

            <flux:navbar.item
                icon="photo"
                wire:click="setTab('branding')"
                :current="$tab === 'branding'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('Branding & PWA') }}
            </flux:navbar.item>

            <flux:navbar.item
                icon="globe-alt"
                wire:click="setTab('footer')"
                :current="$tab === 'footer'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('Footer & Social') }}
            </flux:navbar.item>

            <flux:navbar.item
                icon="question-mark-circle"
                wire:click="setTab('faqs')"
                :current="$tab === 'faqs'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('FAQs') }}
                <flux:badge size="xs" color="zinc" class="ml-1">{{ $this->faqs->count() }}</flux:badge>
            </flux:navbar.item>

            <flux:navbar.item
                icon="wrench-screwdriver"
                wire:click="setTab('maintenance')"
                :current="$tab === 'maintenance'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('Maintenance') }}
                @if ($maintenanceEnabled)
                    <flux:badge size="xs" color="amber" class="ml-1">{{ __('503') }}</flux:badge>
                @endif
            </flux:navbar.item>

            <flux:navbar.item
                icon="envelope"
                wire:click="setTab('inquiries')"
                :current="$tab === 'inquiries'"
                class="shrink-0 whitespace-nowrap cursor-pointer"
            >
                {{ __('Inquiries') }}
                @if ($this->contactMessages->isNotEmpty())
                    <flux:badge size="xs" color="accent" class="ml-1">{{ $this->contactMessages->count() }}</flux:badge>
                @endif
            </flux:navbar.item>
        </flux:navbar>
    </div>

    {{-- Tab 1: General & Instance Configuration --}}
    <div x-show="$wire.tab === 'general'" :class="$wire.tab === 'general' ? '' : 'hidden'" class="space-y-6">
        <form wire:submit="saveSettings" class="space-y-6">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-6 dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Instance configuration') }}</flux:heading>
                    <flux:subheading>{{ __('Core metadata, site name, support email, and default appearance.') }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input
                        wire:model="siteName"
                        :label="__('Site title')"
                        :placeholder="__('Vaulta')"
                        required
                    />

                    <flux:input
                        wire:model="supportEmail"
                        type="email"
                        :label="__('Support email')"
                        :placeholder="__('support@vaulta.local')"
                        required
                    />

                    <flux:input
                        wire:model="issuerName"
                        :label="__('Default OTP issuer name')"
                        :placeholder="__('Vaulta')"
                        required
                    />

                    <flux:select
                        wire:model="defaultTheme"
                        :label="__('Default visitor theme')"
                    >
                        @foreach (Palette::cases() as $palette)
                            <flux:select.option :value="$palette->value">
                                {{ $palette->label() }} ({{ $palette->isDark() ? __('Dark') : __('Light') }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:textarea
                        wire:model="siteDescription"
                        :label="__('Instance description & meta tagline')"
                        :placeholder="__('Enterprise self-hosted two-factor authenticator with zero telemetry.')"
                        rows="3"
                    />
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save general settings') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>

    {{-- Tab 2: Security & Authentication Policies --}}
    <div x-show="$wire.tab === 'security'" :class="$wire.tab === 'security' ? '' : 'hidden'" class="space-y-6">
        <form wire:submit="saveSettings" class="space-y-6">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-6 dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Access & security policies') }}</flux:heading>
                    <flux:subheading>{{ __('Manage account registration rules, email verification enforcement, and vault export controls.') }}</flux:subheading>
                </div>

                {{-- User Registration Policy --}}
                <div class="space-y-3">
                    <flux:label>{{ __('User registration policy') }}</flux:label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ([
                            'open' => ['title' => __('Open'), 'desc' => __('Anyone can register an account.')],
                            'first_user' => ['title' => __('First user only'), 'desc' => __('Closes automatically once first account is created.')],
                            'closed' => ['title' => __('Closed'), 'desc' => __('Sign-ups disabled. Only admins create accounts.')],
                        ] as $modeKey => $modeData)
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                                :class="$wire.registrationMode === '{{ $modeKey }}' ? 'border-accent bg-accent/10' : 'border-zinc-200 dark:border-zinc-700 bg-white/50 dark:bg-zinc-950/40'"
                            >
                                <input
                                    type="radio"
                                    name="registration_mode"
                                    value="{{ $modeKey }}"
                                    wire:model.live="registrationMode"
                                    class="mt-0.5 text-accent focus:ring-accent"
                                />
                                <div class="text-xs">
                                    <div class="font-semibold">{{ $modeData['title'] }}</div>
                                    <div class="mt-0.5 opacity-75">{{ $modeData['desc'] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Email Verification Enforcement (Featured Setting) --}}
                <div class="rounded-xl border border-zinc-200 bg-white/70 p-5 space-y-4 dark:border-zinc-700 dark:bg-zinc-950/60">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <flux:heading size="base" class="flex items-center gap-2">
                                <flux:icon name="envelope" class="size-4 text-accent" />
                                {{ __('Email verification enforcement') }}
                            </flux:heading>
                            <flux:subheading class="text-xs">
                                {{ __('When enabled, users must click the verification link sent to their email before accessing vaults. Disable for air-gapped, offline, or SMTP-less environments.') }}
                            </flux:subheading>
                        </div>
                        <flux:switch wire:model="requireEmailVerification" />
                    </div>

                    <div class="rounded-lg bg-zinc-100/80 p-3 text-xs dark:bg-zinc-900/80">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-[11px] font-mono text-zinc-600 dark:text-zinc-400">
                            <span class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full {{ $requireEmailVerification ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                {{ __('Status:') }}
                                <strong class="{{ $requireEmailVerification ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500' }}">
                                    {{ $requireEmailVerification ? __('Active (Verification required)') : __('Inactive (Immediate access)') }}
                                </strong>
                            </span>
                            <span>
                                {{ __('Mail driver:') }} <code class="rounded bg-zinc-200/80 px-1 py-0.5 dark:bg-zinc-800">{{ config('mail.default') }}</code>
                            </span>
                        </div>
                        @if (config('mail.default') === 'log' || config('mail.default') === 'array')
                            <div class="mt-2 text-[11px] text-amber-700 dark:text-amber-400">
                                {{ __('Note: Current mail driver is local. Sent verification links will appear in storage/logs/laravel.log.') }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Plaintext Export --}}
                <div class="rounded-xl border border-zinc-200 bg-white/70 p-4 space-y-2 dark:border-zinc-700 dark:bg-zinc-950/60">
                    <flux:checkbox
                        wire:model="allowPlainExport"
                        :label="__('Allow unencrypted plaintext JSON vault exports')"
                        :description="__('When unchecked, users must provide a password to AES-256 encrypt all exported vaults.')"
                    />
                </div>

                {{-- Session & Lockout Parameters --}}
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 pt-2">
                    <flux:input
                        wire:model="sessionLifetime"
                        type="number"
                        min="5"
                        max="10080"
                        :label="__('Session idle lifetime (minutes)')"
                        :description="__('How long an inactive user session remains valid.')"
                    />

                    <flux:input
                        wire:model="maxLoginAttempts"
                        type="number"
                        min="1"
                        max="50"
                        :label="__('Max sign-in attempts before lockout')"
                        :description="__('Number of failed password attempts before temporary sign-in throttling.')"
                    />
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save security policies') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>

    {{-- Tab 3: Branding & PWA Assets --}}
    <div x-show="$wire.tab === 'branding'" :class="$wire.tab === 'branding' ? '' : 'hidden'" class="space-y-6">
        <form wire:submit="saveSettings" class="space-y-6">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-6 dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Logo, favicon & PWA branding') }}</flux:heading>
                    <flux:subheading>{{ __('Customize the visual identity for browser tabs, headers, and installed PWA app shortcuts.') }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                    {{-- Logo Upload / Preview --}}
                    <div class="space-y-3">
                        <flux:label>{{ __('Site header logo') }}</flux:label>
                        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white/60 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent/10 border border-zinc-200 p-1 dark:border-zinc-700">
                                @if ($logoFile)
                                    <img src="{{ $logoFile->temporaryUrl() }}" alt="Preview" class="size-full object-contain" />
                                @elseif ($siteLogo)
                                    <img src="{{ $siteLogo }}" alt="Logo" class="size-full object-contain" />
                                @else
                                    <x-app-logo-icon class="size-7 text-accent" />
                                @endif
                            </div>

                            <div class="min-w-0 flex-1 space-y-2">
                                <input
                                    type="file"
                                    wire:model="logoFile"
                                    accept="image/*"
                                    class="block w-full text-xs text-zinc-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-accent file:text-accent-foreground hover:file:opacity-90 cursor-pointer"
                                />
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] opacity-70">{{ __('PNG, SVG, or WebP up to 2MB') }}</span>
                                    @if ($siteLogo || $logoFile)
                                        <flux:button size="xs" variant="ghost" wire:click="removeLogo">
                                            {{ __('Reset') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Favicon Upload / Preview --}}
                    <div class="space-y-3">
                        <flux:label>{{ __('Favicon & PWA icon') }}</flux:label>
                        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white/60 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent/10 border border-zinc-200 p-1 dark:border-zinc-700">
                                @if ($faviconFile)
                                    <img src="{{ $faviconFile->temporaryUrl() }}" alt="Preview" class="size-full object-contain" />
                                @elseif ($siteFavicon)
                                    <img src="{{ $siteFavicon }}" alt="Favicon" class="size-full object-contain" />
                                @else
                                    <img src="/favicon.svg" alt="Default" class="size-7" />
                                @endif
                            </div>

                            <div class="min-w-0 flex-1 space-y-2">
                                <input
                                    type="file"
                                    wire:model="faviconFile"
                                    accept=".png,.ico,.svg,.webp"
                                    class="block w-full text-xs text-zinc-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-accent file:text-accent-foreground hover:file:opacity-90 cursor-pointer"
                                />
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] opacity-70">{{ __('SVG, PNG, ICO, or WebP up to 1MB') }}</span>
                                    @if ($siteFavicon || $faviconFile)
                                        <flux:button size="xs" variant="ghost" wire:click="removeFavicon">
                                            {{ __('Reset') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PWA Manifest & Diagnostics --}}
                <div class="rounded-xl border border-zinc-200 bg-white/60 p-4 text-xs dark:border-zinc-700 dark:bg-zinc-950/40">
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon name="device-phone-mobile" class="size-4 text-accent" />
                        {{ __('Progressive Web App (PWA) assets') }}
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-zinc-600 dark:text-zinc-400">
                        <span>{{ __('Web manifest:') }} <a href="/manifest.webmanifest" target="_blank" class="underline text-accent">/manifest.webmanifest</a></span>
                        <span>{{ __('Service worker:') }} <a href="/sw.js" target="_blank" class="underline text-accent">/sw.js</a></span>
                        <flux:badge size="xs" color="emerald">{{ __('Offline cache enabled') }}</flux:badge>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save branding & assets') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>

    {{-- Tab 4: Footer & Social Links --}}
    <div x-show="$wire.tab === 'footer'" :class="$wire.tab === 'footer' ? '' : 'hidden'" class="space-y-6">
        <form wire:submit="saveSettings" class="space-y-6">
            {{-- Footer write-up --}}
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-6 dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Footer write-up & legal lines') }}</flux:heading>
                    <flux:subheading>{{ __('Customize the copyright notices and license badges shown across all guest pages and footers.') }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input
                        wire:model="footerCopyright"
                        :label="__('Footer copyright / left notice')"
                        :placeholder="__('Vaulta · AGPL-3.0')"
                    />

                    <flux:input
                        wire:model="footerTagline"
                        :label="__('Footer right write-up / badge')"
                        :placeholder="__('RFC 6238 · Zero telemetry')"
                    />
                </div>
            </div>

            {{-- Social Links --}}
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-6 dark:border-zinc-700 dark:bg-zinc-900/80">
                <div class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Social media & community links') }}</flux:heading>
                    <flux:subheading>{{ __('Links displayed in the footer for your instance community.') }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input
                        wire:model="socialGithub"
                        :label="__('GitHub URL')"
                        placeholder="https://github.com/..."
                    />

                    <flux:input
                        wire:model="socialTwitter"
                        :label="__('X (Twitter) URL')"
                        placeholder="https://x.com/..."
                    />

                    <flux:input
                        wire:model="socialDiscord"
                        :label="__('Discord invite URL')"
                        placeholder="https://discord.gg/..."
                    />

                    <flux:input
                        wire:model="socialBluesky"
                        :label="__('Bluesky profile URL')"
                        placeholder="https://bsky.app/profile/..."
                    />
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save footer & social') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>

    {{-- Tab 5: FAQ Management --}}
    <div x-show="$wire.tab === 'faqs'" :class="$wire.tab === 'faqs' ? '' : 'hidden'" class="space-y-6">
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-6 dark:border-zinc-700 dark:bg-zinc-900/80">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                <div>
                    <flux:heading size="lg">{{ __('Frequently Asked Questions (FAQ)') }}</flux:heading>
                    <flux:subheading>{{ __('These questions and answers appear on the public /faq page and within the offline PWA shell.') }}</flux:subheading>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        wire:click="resetDefaultFaqs"
                        wire:confirm="{{ __('Reset all FAQs to the default set?') }}"
                    >
                        {{ __('Reset defaults') }}
                    </flux:button>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="primary"
                        icon="plus"
                        wire:click="$set('showAddFaqModal', true)"
                    >
                        {{ __('Add question') }}
                    </flux:button>
                </div>
            </div>

            @if ($this->faqs->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-xs opacity-75 dark:border-zinc-700">
                    {{ __('No FAQs configured. Click "Add question" or "Reset defaults".') }}
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($this->faqs as $index => $item)
                        <div wire:key="faq-row-{{ $item->id }}" class="rounded-xl border border-zinc-200 bg-white/70 p-4 space-y-3 dark:border-zinc-700 dark:bg-zinc-950/60 text-xs">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded bg-accent/15 text-accent font-bold font-mono text-[10px]">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="font-semibold text-sm truncate text-zinc-900 dark:text-zinc-100">
                                        {{ $item->question }}
                                    </div>
                                    @if (! $item->is_published)
                                        <flux:badge size="xs" color="zinc">{{ __('Draft') }}</flux:badge>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        icon="chevron-up"
                                        wire:click="moveFaqUp({{ $item->id }})"
                                        :disabled="$index === 0"
                                    />
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        icon="chevron-down"
                                        wire:click="moveFaqDown({{ $item->id }})"
                                        :disabled="$index === $this->faqs->count() - 1"
                                    />
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        icon="pencil-square"
                                        wire:click="editFaq({{ $item->id }})"
                                    />
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="deleteFaq({{ $item->id }})"
                                        wire:confirm="{{ __('Delete this question?') }}"
                                    />
                                </div>
                            </div>

                            <div class="rounded-lg bg-zinc-100/70 p-3 text-xs leading-relaxed dark:bg-zinc-900/70 text-zinc-600 dark:text-zinc-300 whitespace-pre-line">
                                {{ $item->answer }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Tab 6: Maintenance Mode --}}
    <div x-show="$wire.tab === 'maintenance'" :class="$wire.tab === 'maintenance' ? '' : 'hidden'" class="space-y-6">
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 dark:border-zinc-700 dark:bg-zinc-900/80">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <flux:heading size="lg">{{ __('Maintenance mode') }}</flux:heading>
                        @if ($maintenanceEnabled)
                            <flux:badge size="sm" color="amber">{{ __('ACTIVE (503)') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="emerald">{{ __('Online') }}</flux:badge>
                        @endif
                    </div>
                    <flux:subheading>
                        {{ __('Take Vaulta offline for upgrades. Administrators retain access to the admin panel.') }}
                    </flux:subheading>
                </div>

                <div>
                    @if ($maintenanceEnabled)
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="arrow-path"
                            wire:click="toggleMaintenance"
                            wire:confirm="{{ __('Bring the site back online for all users?') }}"
                        >
                            {{ __('Disable maintenance mode') }}
                        </flux:button>
                    @else
                        <flux:button
                            type="button"
                            variant="danger"
                            icon="shield-exclamation"
                            wire:click="$set('showMaintenanceModal', true)"
                        >
                            {{ __('Enable maintenance mode') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($maintenanceEnabled)
                <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs dark:bg-amber-500/5">
                    <div class="font-semibold text-amber-800 dark:text-amber-300">
                        {{ __('Active bypass URL for authorized staff:') }}
                    </div>
                    <div class="mt-1 flex items-center gap-2 font-mono">
                        <span class="select-all underline">{{ url('/' . $maintenanceSecret) }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Tab 7: Contact Form Inquiries --}}
    <div x-show="$wire.tab === 'inquiries'" :class="$wire.tab === 'inquiries' ? '' : 'hidden'" class="space-y-6">
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 space-y-4 dark:border-zinc-700 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Recent contact inquiries') }}</flux:heading>
                    <flux:subheading>{{ __('Messages submitted via the public /contact page.') }}</flux:subheading>
                </div>
                <flux:badge size="sm">{{ $this->contactMessages->count() }} {{ __('messages') }}</flux:badge>
            </div>

            @if ($this->contactMessages->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-xs opacity-75 dark:border-zinc-700">
                    {{ __('No contact messages submitted yet.') }}
                </div>
            @else
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white/70 text-xs dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-950/60">
                    @foreach ($this->contactMessages as $inquiry)
                        <div wire:key="msg-{{ $inquiry->id }}" class="p-4 space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm">{{ $inquiry->name }}</span>
                                    <span class="font-mono opacity-70">&lt;{{ $inquiry->email }}&gt;</span>
                                    @if ($inquiry->user_id)
                                        <flux:badge size="sm" color="emerald">{{ __('Verified user') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-[11px] opacity-60">{{ $inquiry->created_at?->diffForHumans() }}</span>
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="deleteMessage({{ $inquiry->id }})"
                                        wire:confirm="{{ __('Delete this message?') }}"
                                    />
                                </div>
                            </div>
                            <div class="font-medium text-accent-content">
                                {{ $inquiry->subject }}
                            </div>
                            <div class="rounded-lg bg-zinc-100/70 p-3 text-xs leading-relaxed dark:bg-zinc-900/70 whitespace-pre-line">
                                {{ $inquiry->message }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Add FAQ Modal --}}
    <flux:modal wire:model="showAddFaqModal" class="w-full max-w-lg">
        <form wire:submit="addFaq" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Add frequently asked question') }}</flux:heading>
                <flux:subheading>{{ __('Provide the question and detailed markdown or plain text explanation.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="newQuestion"
                :label="__('Question')"
                :placeholder="__('e.g., How do I scan QR codes on mobile?')"
                required
            />

            <flux:textarea
                wire:model="newAnswer"
                :label="__('Answer')"
                :placeholder="__('Detailed answer text...')"
                rows="4"
                required
            />

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showAddFaqModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Add FAQ') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit FAQ Modal --}}
    <flux:modal wire:model="showEditFaqModal" class="w-full max-w-lg">
        <form wire:submit="updateFaq" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Edit FAQ') }}</flux:heading>
                <flux:subheading>{{ __('Update question or answer text.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="editingQuestion"
                :label="__('Question')"
                required
            />

            <flux:textarea
                wire:model="editingAnswer"
                :label="__('Answer')"
                rows="4"
                required
            />

            <flux:checkbox
                wire:model="editingIsPublished"
                :label="__('Publish this question on the public /faq page')"
            />

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showEditFaqModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Update FAQ') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Maintenance Configuration Modal --}}
    <flux:modal wire:model="showMaintenanceModal" class="w-full max-w-md">
        <form wire:submit="toggleMaintenance" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Enable maintenance mode') }}</flux:heading>
                <flux:subheading>{{ __('Set a bypass secret so administrators can still browse and test the site.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="maintenanceSecret"
                :label="__('Secret bypass phrase / URL slug')"
                :placeholder="__('e.g., secret-vaulta-access')"
                required
            />

            <flux:input
                wire:model="maintenanceMessage"
                :label="__('503 status message')"
                :placeholder="__('Vaulta is temporarily offline for maintenance.')"
            />

            <flux:input
                wire:model="maintenanceRetry"
                type="number"
                :label="__('Retry-After header (seconds)')"
                min="10"
                max="86400"
            />

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showMaintenanceModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="danger">
                    {{ __('Go offline (503)') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-admin.nav>
