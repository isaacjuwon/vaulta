<?php

declare(strict_types=1);

use App\Http\Controllers\OAuthController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get(uri: '/', action: static function (): View|RedirectResponse {
    if (Auth::check()) {
        return redirect()->route(route: 'vault');
    }

    return view(view: 'welcome');
})->name(name: 'home');

Route::livewire(uri: '/about', component: 'pages::about')->name(name: 'about');
Route::livewire(uri: '/faq', component: 'pages::faq')->name(name: 'faq');
Route::livewire(uri: '/privacy', component: 'pages::privacy')->name(name: 'privacy');
Route::livewire(uri: '/terms', component: 'pages::terms')->name(name: 'terms');
Route::livewire(uri: '/contact', component: 'pages::contact')->name(name: 'contact');

Route::get(uri: '/sitemap.xml', action: static function (): Response {
    return response()
        ->view(view: 'sitemap')
        ->header(key: 'Content-Type', values: 'application/xml; charset=UTF-8');
})->name(name: 'sitemap');

// OAuth authentication & account linking
Route::get(uri: '/oauth/{provider}/redirect', action: [OAuthController::class, 'redirect'])->name(name: 'oauth.redirect');
Route::get(uri: '/oauth/{provider}/callback', action: [OAuthController::class, 'callback'])->name(name: 'oauth.callback');

Route::middleware(['auth', 'verified', 'not-blocked'])->group(callback: static function (): void {
    Route::livewire(uri: '/account/vault', component: 'pages::vault.index')->name(name: 'vault');

    Route::get(uri: '/vault', action: static function (Request $request): RedirectResponse {
        return redirect()->route(route: 'vault', parameters: $request->query());
    })->name(name: 'vault.redirect');

    Route::get(uri: '/dashboard', action: static function (Request $request): RedirectResponse {
        return redirect()->route(route: 'vault', parameters: $request->query());
    })->name(name: 'dashboard');
});

Route::prefix('admin')
    ->middleware(['auth', 'not-blocked', 'admin'])
    ->group(callback: static function (): void {
        Route::redirect(uri: '/', destination: '/admin/dashboard');
        Route::livewire(uri: '/dashboard', component: 'pages::admin.dashboard')->name(name: 'admin.dashboard');
        Route::livewire(uri: '/users', component: 'pages::admin.users')->name(name: 'admin.users');
        Route::livewire(uri: '/authenticators', component: 'pages::admin.authenticators')->name(name: 'admin.authenticators');
        Route::livewire(uri: '/sessions', component: 'pages::admin.sessions')->name(name: 'admin.sessions');
        Route::livewire(uri: '/settings', component: 'pages::admin.settings')->name(name: 'admin.settings');
    });

require __DIR__.'/settings.php';
