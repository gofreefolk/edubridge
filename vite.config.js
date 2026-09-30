import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // The build lives under /build, but the service worker must control the whole
        // site, so it is served from /sw.js by a Laravel route (see routes/web.php) and
        // registered in app.js. Precache URLs are therefore prefixed with /build/.
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: null,
            scope: '/',
            includeAssets: ['favicon.ico'],
            manifest: {
                id: '/',
                name: 'EduBridge',
                short_name: 'EduBridge',
                description: 'Official school communication for Kerala schools',
                theme_color: '#1e40af',
                background_color: '#f8fafc',
                display: 'standalone',
                orientation: 'portrait',
                lang: 'ml',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
                    { src: '/icons/icon-maskable-192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' },
                    { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,ico,png,svg,woff2}'],
                modifyURLPrefix: { '': '/build/' },
                inlineWorkboxRuntime: true,
                // The HTML shell is rendered by Laravel, so it is not precached; cache it
                // network-first so the app still opens offline after a first visit.
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: ({ request, url }) => request.mode === 'navigate'
                            && !url.pathname.startsWith('/api/')
                            && url.pathname !== '/up',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages',
                            networkTimeoutSeconds: 5,
                            expiration: { maxEntries: 20 },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'fonts-cache',
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
