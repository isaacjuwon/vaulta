import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                local('JetBrains Mono', {
                    preload: [{ weight: 400 }, { weight: 600 }],
                    variants: [
                        { src: 'resources/fonts/jetbrains-mono/jetbrains-mono-400.woff2', weight: 400 },
                        { src: 'resources/fonts/jetbrains-mono/jetbrains-mono-500.woff2', weight: 500 },
                        { src: 'resources/fonts/jetbrains-mono/jetbrains-mono-600.woff2', weight: 600 },
                        { src: 'resources/fonts/jetbrains-mono/jetbrains-mono-700.woff2', weight: 700 },
                    ],
                }),
                local('Public Sans', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/public-sans/public-sans-400.woff2', weight: 400 },
                        { src: 'resources/fonts/public-sans/public-sans-500.woff2', weight: 500 },
                        { src: 'resources/fonts/public-sans/public-sans-600.woff2', weight: 600 },
                        { src: 'resources/fonts/public-sans/public-sans-700.woff2', weight: 700 },
                    ],
                }),
                local('Pixelify Sans', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/pixelify-sans/pixelify-sans-500.woff2', weight: 500 },
                        { src: 'resources/fonts/pixelify-sans/pixelify-sans-600.woff2', weight: 600 },
                        { src: 'resources/fonts/pixelify-sans/pixelify-sans-700.woff2', weight: 700 },
                    ],
                }),
                local('Tinos', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/tinos/tinos-400.woff2', weight: 400 },
                        { src: 'resources/fonts/tinos/tinos-700.woff2', weight: 700 },
                    ],
                }),
                local('Courier Prime', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/courier-prime/courier-prime-400.woff2', weight: 400 },
                        { src: 'resources/fonts/courier-prime/courier-prime-700.woff2', weight: 700 },
                    ],
                }),
                local('Quicksand', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/quicksand/quicksand-400.woff2', weight: 400 },
                        { src: 'resources/fonts/quicksand/quicksand-500.woff2', weight: 500 },
                        { src: 'resources/fonts/quicksand/quicksand-600.woff2', weight: 600 },
                        { src: 'resources/fonts/quicksand/quicksand-700.woff2', weight: 700 },
                    ],
                }),
                local('Fredoka', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/fredoka/fredoka-500.woff2', weight: 500 },
                        { src: 'resources/fonts/fredoka/fredoka-600.woff2', weight: 600 },
                        { src: 'resources/fonts/fredoka/fredoka-700.woff2', weight: 700 },
                    ],
                }),
                local('Poppins', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/poppins/poppins-400.woff2', weight: 400 },
                        { src: 'resources/fonts/poppins/poppins-500.woff2', weight: 500 },
                        { src: 'resources/fonts/poppins/poppins-600.woff2', weight: 600 },
                        { src: 'resources/fonts/poppins/poppins-700.woff2', weight: 700 },
                    ],
                }),
                local('DotGothic16', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/dotgothic16/dotgothic16-400.woff2', weight: 400 },
                    ],
                }),
                local('Jersey 10', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/jersey-10/jersey-10-400.woff2', weight: 400 },
                    ],
                }),
                local('IBM Plex Mono', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/ibm-plex-mono/ibm-plex-mono-400.woff2', weight: 400 },
                        { src: 'resources/fonts/ibm-plex-mono/ibm-plex-mono-500.woff2', weight: 500 },
                        { src: 'resources/fonts/ibm-plex-mono/ibm-plex-mono-700.woff2', weight: 700 },
                    ],
                }),
                local('VT323', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/vt323/vt323-400.woff2', weight: 400 },
                    ],
                }),
                local('Press Start 2P', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/press-start-2p/press-start-2p-400.woff2', weight: 400 },
                    ],
                }),
                local('Share Tech Mono', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/share-tech-mono/share-tech-mono-400.woff2', weight: 400 },
                    ],
                }),
                local('Silkscreen', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/silkscreen/silkscreen-400.woff2', weight: 400 },
                        { src: 'resources/fonts/silkscreen/silkscreen-700.woff2', weight: 700 },
                    ],
                }),
                local('Outfit', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/outfit/outfit-400.woff2', weight: 400 },
                        { src: 'resources/fonts/outfit/outfit-500.woff2', weight: 500 },
                        { src: 'resources/fonts/outfit/outfit-600.woff2', weight: 600 },
                        { src: 'resources/fonts/outfit/outfit-700.woff2', weight: 700 },
                    ],
                }),
                local('Righteous', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/righteous/righteous-400.woff2', weight: 400 },
                    ],
                }),
                local('Libre Franklin', {
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/libre-franklin/libre-franklin-400.woff2', weight: 400 },
                        { src: 'resources/fonts/libre-franklin/libre-franklin-500.woff2', weight: 500 },
                        { src: 'resources/fonts/libre-franklin/libre-franklin-600.woff2', weight: 600 },
                        { src: 'resources/fonts/libre-franklin/libre-franklin-700.woff2', weight: 700 },
                    ],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        host: '127.0.0.1',
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/ferrite/**',
                '**/wellto.io/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
