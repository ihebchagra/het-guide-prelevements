const CACHE_VERSION = '1.6'; // <-- IMPORTANT: Change this number whenever you update any file
const CACHE_NAME = `guide-prelevements-v${CACHE_VERSION}`;

// We add query strings to these files to ensure we bust the browser's HTTP cache
// when the service worker updates.
const urlsToCache = [
    '/', // Cache the root URL, which serves index.php
    'css/style.css?v=' + CACHE_VERSION,
    'js/alpine.min.js', // This library file won't change often
    'js/sql-wasm.js',
    'js/sql-wasm.wasm',
    'js/app.js?v=' + CACHE_VERSION,
    'js/viewer-zoom.js?v=' + CACHE_VERSION,
    'assets/logo.svg',
    'assets/db/guide_prelevements.db?v=' + CACHE_VERSION
];

// 1. Installation: Cache the "app shell"
self.addEventListener('install', event => {
    self.skipWaiting(); // Force the new service worker to activate immediately
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache and caching app shell');
                return cache.addAll(urlsToCache);
            })
            .catch(error => {
                console.error('Failed to cache app shell:', error);
                // This will show which file failed if you inspect the network tab during installation
            })
    );
});

// 2. Activation: Clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            ).then(() => self.clients.claim()); // Take control of all open pages
        })
    );
});

// 3. Fetch: Implement Stale-While-Revalidate strategy
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }

    // For HTML pages, use a Network First strategy to ensure users get updates quickly
    // For other assets, use Stale-While-Revalidate
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/'))
        );
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(cache => {
            return cache.match(event.request).then(response => {
                const fetchPromise = fetch(event.request).then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                });
                return response || fetchPromise;
            });
        })
    );
});
