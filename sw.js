// GMS App — Service Worker
// Purpose: makes the site installable as an app, and caches only static assets
// (css/js/images/fonts) so the app shell loads instantly and works offline-ish.
// IMPORTANT: dynamic PHP pages (dashboards, notices, marks, etc.) are always
// fetched fresh from the network — never cached — so data is never stale.

const CACHE_NAME = 'gms-app-shell-v2';

// self.location reflects wherever this file was actually registered from
// (root, /gms/, /ganeshman_school/, etc.), so these resolve correctly no
// matter what subdirectory the project is deployed into.
const BASE = new URL('./', self.location).href;
const STATIC_ASSETS = [
    BASE + 'assets/css/style.css',
    BASE + 'assets/js/main.js',
    BASE + 'assets/images/gms.png',
    BASE + 'assets/images/gms1.png',
    BASE + 'assets/images/gmscrop.png',
    BASE + 'assets/icons/icon-192.png',
    BASE + 'assets/icons/icon-512.png',
    BASE + 'manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .catch(() => { /* ignore individual asset failures */ })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Only intercept same-origin static files. Everything else (all .php pages,
    // AJAX calls, API requests, cross-origin video/CDN) goes straight to network.
    const isStatic = /\.(css|js|png|jpg|jpeg|svg|webp|gif|ico|woff2?|ttf)$/i.test(url.pathname);
    if (url.origin !== self.location.origin || !isStatic) return;

    event.respondWith(
        caches.match(req).then((cached) => {
            const networkFetch = fetch(req)
                .then((res) => {
                    if (res && res.status === 200) {
                        const clone = res.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
                    }
                    return res;
                })
                .catch(() => cached);
            // Stale-while-revalidate: show cached instantly, refresh in background
            return cached || networkFetch;
        })
    );
});