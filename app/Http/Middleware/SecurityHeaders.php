<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();

        $response = $next($request);

        $isHot = Vite::isRunningHot();

        // W3C CSP host-source grammar forbids IPv6 bracket literals like http://[::1]:5173,
        // which Vite writes to public/hot on Windows/IPv6 loopback. When the local Vite HMR
        // server is active, use valid scheme-sources (http:/https:/ws:/wss:); in production
        // builds, enforce strict 'self' + per-request nonce + 'strict-dynamic'.
        $scriptPolicy = $isHot
            ? "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' http: https:"
            : "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval'";

        $stylePolicy = $isHot
            ? "style-src 'self' 'unsafe-inline' http: https:"
            : "style-src 'self' 'unsafe-inline'";

        $fontPolicy = $isHot
            ? "font-src 'self' data: http: https:"
            : "font-src 'self' data:";

        $imgPolicy = $isHot
            ? "img-src 'self' data: blob: http: https:"
            : "img-src 'self' data: blob:";

        $connectPolicy = $isHot
            ? "connect-src 'self' http: https: ws: wss:"
            : "connect-src 'self'";

        $contentSecurityPolicy = implode(separator: '; ', array: [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            $scriptPolicy,
            "worker-src 'self' blob:",
            $stylePolicy,
            $fontPolicy,
            $imgPolicy,
            $connectPolicy,
        ]);

        $response->headers->set(key: 'X-Content-Type-Options', values: 'nosniff');
        $response->headers->set(key: 'X-Frame-Options', values: 'DENY');
        $response->headers->set(key: 'Referrer-Policy', values: 'strict-origin-when-cross-origin');
        $response->headers->set(key: 'Permissions-Policy', values: 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(key: 'X-Permitted-Cross-Domain-Policies', values: 'none');
        $response->headers->set(key: 'Content-Security-Policy', values: $contentSecurityPolicy);

        if ($request->isSecure()) {
            $response->headers->set(key: 'Strict-Transport-Security', values: 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() !== null) {
            $response->headers->set(key: 'Cache-Control', values: 'private, no-store, no-cache, must-revalidate');
            $response->headers->set(key: 'Pragma', values: 'no-cache');
        }

        return $response;
    }
}
