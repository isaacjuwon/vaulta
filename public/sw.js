// Vaulta Offline Shell Service Worker
const CACHE_NAME = 'vaulta-shell-v3';
const STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/favicon.svg',
    '/favicon.ico',
    '/apple-touch-icon.png',
    '/icon-192x192.png',
    '/icon-512x512.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Cache-first for compiled assets (Vite content hashes), fonts, branding and static assets
    if (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/resources/fonts/') ||
        url.pathname.startsWith('/uploads/branding/') ||
        url.pathname.endsWith('.woff2') ||
        STATIC_ASSETS.includes(url.pathname)
    ) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached;
                return fetch(event.request).then((response) => {
                    if (response.ok) {
                        const cloned = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, cloned));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Network-first for navigation and dynamic requests, fallback to offline shell if network drops
    event.respondWith(
        fetch(event.request).catch(async () => {
            const cached = await caches.match(event.request);
            if (cached) return cached;

            if (event.request.mode === 'navigate') {
                return new Response(
                    '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vaulta - Offline</title><style>body{margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:system-ui,-apple-system,sans-serif;background:#09090b;color:#f4f4f5;text-align:center;padding:1.5rem}h1{font-size:1.5rem;font-weight:700;margin-bottom:0.5rem}p{color:#a1a1aa;font-size:0.95rem;max-width:28rem;margin:0 auto 1.5rem}button{padding:0.6rem 1.25rem;border-radius:0.5rem;background:#2563eb;color:#fff;border:none;font-weight:600;cursor:pointer}</style></head><body><div><h1>You are offline</h1><p>Vaulta is offline or unreachable. Please reconnect to the network to refresh your session.</p><button onclick="location.reload()">Retry Connection</button></div></body></html>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            }

            return new Response('', { status: 408, statusText: 'Request Timed Out' });
        })
    );
});
