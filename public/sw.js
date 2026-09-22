// شمس — service worker. Two strategies only, kept deliberately simple:
//
// 1. Built, fingerprinted assets (/build/**: filenames change on every `npm run build`) are
//    cache-first: once fetched they never change, so there is no reason to ever re-fetch them.
// 2. Everything else (pages, the API, admin) is network-first with a cached fallback, so the site
//    still works offline for pages the visitor already opened, but never SERVES stale HTML/JSON
//    while online — dynamic and authenticated content must never be cache-first.
//
// Bump CACHE_VERSION when this file's logic changes; that alone forces every client to drop its
// old cache on the next visit (see the "activate" handler).
const CACHE_VERSION = 'shams-v1';

const isBuildAsset = (url) => url.pathname.startsWith('/build/');

// Never cache these, even under the network-first strategy: they are either sensitive
// (auth/admin/dashboard), meant to always be live (the API), or not real pages (webhooks).
const NEVER_CACHE = ['/admin', '/dashboard', '/api/', '/payments/webhook', '/sanctum/'];

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only ever handle same-origin GET requests; everything else goes straight to the network
    // untouched (POST/PUT/DELETE must never be served from a cache).
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (isBuildAsset(url)) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (NEVER_CACHE.some((prefix) => url.pathname.startsWith(prefix))) {
        return;
    }

    event.respondWith(networkFirst(request));
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_VERSION);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirst(request) {
    const cache = await caches.open(CACHE_VERSION);

    try {
        const response = await fetch(request);

        if (response.ok) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        throw error;
    }
}
