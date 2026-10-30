@php
    use App\Enums\Palette;
    use App\Settings\SiteSettings;
    use Illuminate\Support\Facades\Vite;
    use Illuminate\Support\Js;

    $settings = app(SiteSettings::class);
    $customFavicon = $settings->faviconUrl;
@endphp
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="robots" content="{{ request()->routeIs('home') ? 'index,follow' : 'noindex,nofollow' }}" />

<title>
    {{ filled($title ?? null) ? $title.' — '.config('app.name', 'Vaulta') : config('app.name', 'Vaulta') }}
</title>

@if ($customFavicon)
    <link rel="icon" href="{{ $customFavicon }}">
    <link rel="apple-touch-icon" href="{{ $customFavicon }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
<link rel="manifest" href="/manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Vaulta') }}">
<meta name="vaulta-palette" content="{{ $activePalette->value ?? Palette::current()->value }}">
<meta name="theme-color" content="#09090b" id="vaulta-theme-color">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
<script nonce="{{ Vite::cspNonce() }}">
    (() => {
        const allowed = {{ Js::from(Palette::values()) }};
        const light = {{ Js::from(Palette::lightValues()) }};
        const defaultTheme = {{ Js::from(Palette::default()->value) }};

        window.__applyVaultaPalette = function(preferred) {
            try {
                const root = document.documentElement;
                let slug = preferred;

                if (!slug || !allowed.includes(slug)) {
                    const serverMeta = document.querySelector('meta[name="vaulta-palette"]')?.content;
                    if (serverMeta && allowed.includes(serverMeta)) {
                        slug = serverMeta;
                    } else if (root.dataset.palette && allowed.includes(root.dataset.palette)) {
                        slug = root.dataset.palette;
                    } else {
                        const match = document.cookie.match(/(?:^|;\s*)vaulta_palette=([^;]+)/);
                        if (match && allowed.includes(match[1])) {
                            slug = match[1];
                        } else {
                            const stored = localStorage.getItem('vaulta.palette');
                            slug = (stored && allowed.includes(stored)) ? stored : defaultTheme;
                        }
                    }
                }

                root.dataset.palette = slug;
                root.classList.toggle('dark', !light.includes(slug));

                try {
                    localStorage.setItem('vaulta.palette', slug);
                    document.cookie = 'vaulta_palette=' + slug + ';path=/;max-age=31536000;SameSite=Lax';
                } catch (e) {}
            } catch (e) {}
        };

        // Enforce immediately before any HTML body rendering occurs (zero flicker)
        window.__applyVaultaPalette();

        // Enforce on every Livewire SPA navigation (wire:navigate)
        document.addEventListener('livewire:navigated', () => {
            const nextPalette = document.querySelector('meta[name="vaulta-palette"]')?.content;
            window.__applyVaultaPalette(nextPalette);
        });

        // Enforce when palette is changed anywhere in the app
        window.addEventListener('vaulta-palette-changed', (e) => {
            window.__applyVaultaPalette(e.detail?.palette);
        });

        // Enforce across other browser tabs in real-time
        window.addEventListener('storage', (e) => {
            if (e.key === 'vaulta.palette' && e.newValue) {
                window.__applyVaultaPalette(e.newValue);
            }
        });

        // Capture PWA beforeinstallprompt early so it can be handled by the UI
        window.__vaultaPwaPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.__vaultaPwaPrompt = e;
            window.dispatchEvent(new CustomEvent('vaulta-pwa-ready'));
        });

        window.addEventListener('appinstalled', () => {
            window.__vaultaPwaPrompt = null;
            window.dispatchEvent(new CustomEvent('vaulta-pwa-installed'));
        });

        if ('serviceWorker' in navigator) {
            const registerSw = () => {
                navigator.serviceWorker.register('/sw.js', { scope: '/' })
                    .then((reg) => {
                        console.debug('[Vaulta] Service worker registered with scope:', reg.scope);
                    })
                    .catch((err) => {
                        console.warn('[Vaulta] Service worker registration failed:', err);
                    });
            };

            if (document.readyState === 'complete') {
                registerSw();
            } else {
                window.addEventListener('load', registerSw, { once: true });
            }
        }
    })();
</script>
