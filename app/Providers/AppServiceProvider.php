<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\SiteSettingsUpdated;
use App\Listeners\SyncWebManifest;
use App\Settings\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(
            abstract: SiteSettings::class,
            concrete: static fn (): SiteSettings => SiteSettings::current(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureEvents();
        $this->configureSiteSettings();
    }

    /**
     * Configure event listeners.
     */
    private function configureEvents(): void
    {
        Event::listen(
            events: SiteSettingsUpdated::class,
            listener: SyncWebManifest::class,
        );
    }

    /**
     * Synchronize dynamic site settings into application configuration.
     */
    private function configureSiteSettings(): void
    {
        try {
            $settings = SiteSettings::current();

            config([
                'app.name' => $settings->name,
                'mail.from.address' => $settings->supportEmail,
                'mail.from.name' => $settings->name,
                'vaulta.issuer' => $settings->issuer,
                'fortify.issuer' => $settings->issuer,
                'vaulta.registration' => $settings->registrationMode,
                'vaulta.require_email_verification' => $settings->requireEmailVerification,
            ]);
        } catch (\Throwable) {
            // Settings table not yet available during initial migrations
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    private function configureDefaults(): void
    {
        Date::use(handler: CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            prohibit: app()->isProduction(),
        );

        Password::defaults(callback: fn (): Password => app()->isProduction()
            ? Password::min(size: 12)
                ->max(size: 72)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(size: 8)->max(size: 72),
        );
    }
}
