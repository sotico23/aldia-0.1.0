const CACHE_PREFIX = 'redcliente-pwa-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/apple-touch-icon.png',
    '/icons/pwa-192.png',
    '/icons/pwa-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);

        await cache.addAll(PRECACHE_URLS);
        await precacheViteAssets(cache);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();

        await Promise.all(cacheNames
            .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== CACHE_NAME)
            .map((cacheName) => caches.delete(cacheName)));

        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                return await fetch(request);
            } catch {
                return (await caches.match('/offline.html')) ?? Response.error();
            }
        })());

        return;
    }

    if (esRecursoVersionado(url)) {
        event.respondWith((async () => {
            const cache = await caches.open(CACHE_NAME);
            const cachedResponse = await cache.match(request);

            if (cachedResponse) {
                return cachedResponse;
            }

            const response = await fetch(request);

            if (response.ok) {
                await cache.put(request, response.clone());
            }

            return response;
        })());
    }
});

function esRecursoVersionado(url) {
    return PRECACHE_URLS.includes(url.pathname)
        || (url.pathname.startsWith('/build/assets/')
            && /-[A-Za-z0-9_-]{8,}\.(?:css|js|woff2?|ttf|otf|svg|png|jpe?g|webp)$/.test(url.pathname));
}

async function precacheViteAssets(cache) {
    const response = await fetch('/build/manifest.json', { cache: 'no-store' });

    if (!response.ok) {
        throw new Error(`No se pudo cargar el manifiesto de Vite (${response.status}).`);
    }

    const manifest = await response.json();
    const pending = ['resources/js/app.tsx', 'resources/css/app.css'];
    const visited = new Set();
    const files = new Set();

    while (pending.length > 0) {
        const key = pending.pop();

        if (!key || visited.has(key)) {
            continue;
        }

        visited.add(key);

        const entry = manifest[key];

        if (!entry) {
            continue;
        }

        if (entry.file) {
            files.add(`/build/${entry.file}`);
        }

        for (const stylesheet of entry.css ?? []) {
            files.add(`/build/${stylesheet}`);
        }

        for (const importedKey of entry.imports ?? []) {
            pending.push(importedKey);
        }
    }

    await Promise.all([...files].map(async (file) => {
        const assetResponse = await fetch(file, { cache: 'no-store' });

        if (!assetResponse.ok) {
            throw new Error(`No se pudo precargar el recurso de la aplicación: ${file}`);
        }

        await cache.put(file, assetResponse);
    }));
}
