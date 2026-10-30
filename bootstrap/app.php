<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(callback: function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'vaulta_palette',
        ]);

        $middleware->preventRequestsDuringMaintenance(except: [
            'admin',
            'admin/*',
            'login',
            'login/*',
            'up',
        ]);

        $middleware->append(middleware: SecurityHeaders::class);

        $middleware->alias(aliases: [
            'admin' => EnsureUserIsAdmin::class,
            'not-blocked' => EnsureUserIsNotBlocked::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(using: function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            callback: static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
