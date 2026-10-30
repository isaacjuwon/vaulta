<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\RecordFailedSignIn;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Override;

final class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(callback: ResetUserPassword::class);
        Fortify::createUsersUsing(callback: CreateNewUser::class);
    }

    /**
     * Configure blocked-account and 15-minute lockout enforcement during password authentication.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(callback: static function (Request $request): ?User {
            $rawEmail = $request->input(key: Fortify::username());
            $rawPassword = $request->input(key: 'password');

            $email = is_string($rawEmail) ? mb_strtolower(string: trim(string: $rawEmail)) : '';
            $password = is_string($rawPassword) ? $rawPassword : '';

            if ($email === '' || $password === '') {
                return null;
            }

            $user = User::query()->where(column: 'email', operator: '=', value: $email)->first();

            if ($user?->sign_in_locked_until !== null && now()->lessThan(date: $user->sign_in_locked_until)) {
                throw ValidationException::withMessages(messages: [
                    Fortify::username() => 'Sign-in is temporarily unavailable for security reasons. Please try again in 15 minutes.',
                ]);
            }

            if ($user?->sign_in_locked_until !== null) {
                $user->forceFill(attributes: ['sign_in_locked_until' => null])->save();
            }

            $recorder = new RecordFailedSignIn;

            if ($user === null || $user->is_blocked || $user->password === null || ! Hash::check(value: $password, hashedValue: $user->password)) {
                $recorder->handle(email: $email, user: $user);

                return null;
            }

            RateLimiter::clear(key: RecordFailedSignIn::accountKey(email: $email));

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(
            view: static fn () => Registration::needsInitialSetup()
                ? redirect()->route(route: 'register')
                : view(view: 'pages::auth.login'),
        );

        Fortify::verifyEmailView(view: static fn () => view(view: 'pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(view: static fn () => view(view: 'pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(view: static fn () => view(view: 'pages::auth.confirm-password'));

        Fortify::registerView(view: static function () {
            abort_unless(boolean: Registration::open(), code: 404);

            return view(view: 'pages::auth.register');
        });

        Fortify::resetPasswordView(view: static fn () => view(view: 'pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(view: static fn () => view(view: 'pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for(name: 'two-factor', callback: static function (Request $request): Limit {
            return Limit::perMinute(maxAttempts: 5)->by(key: (string) $request->session()->get(key: 'login.id'));
        });

        RateLimiter::for(name: 'login', callback: static function (Request $request): array {
            $email = is_string($request->input(key: Fortify::username()))
                ? $request->input(key: Fortify::username())
                : '';

            $throttleKey = Str::transliterate(string: Str::lower(value: $email).'|'.$request->ip());

            return [
                Limit::perMinute(maxAttempts: 5)->by(key: $throttleKey),
                Limit::perMinute(maxAttempts: 25)->by(key: 'login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for(name: 'passkeys', callback: static function (Request $request): Limit {
            $credentialId = $request->input(key: 'credential.id');
            $identifier = is_string($credentialId) && $credentialId !== ''
                ? $credentialId
                : $request->session()->getId();

            return Limit::perMinute(maxAttempts: 10)->by(key: $identifier.'|'.$request->ip());
        });
    }
}
