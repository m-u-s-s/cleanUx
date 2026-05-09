/**
 * Phase 8 — Service Worker CleanUx PWA.
 *
 * Responsabilités :
 *   1. Cache des assets statiques (CSS, JS, fonts, icons) → mode offline
 *   2. Network-first pour les pages HTML avec fallback offline.html
 *   3. Réception des push notifications (Web Push API)
 *   4. Click sur notification → focus tab existant ou ouvrir URL
 *
 * IMPORTANT :
 *   - Doit être servi depuis la racine (/sw.js) pour avoir scope="/"
 *   - HTTPS obligatoire en prod (sauf localhost en dev)
 *   - Bump CACHE_VERSION après une grosse MAJ pour forcer un re-cache
 */

const CACHE_VERSION = 'cleanux-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

// On reste minimaliste : Vite génère des noms hashés, le runtime cache
// s'occupera des assets versionnés au fur et à mesure de la navigation.
const PRECACHE_URLS = [
    '/',
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.ico',
];

// ────────────────────────────────────────
// Install : pré-cache des critiques
// ────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
            .catch((err) => console.warn('[SW] precache failed:', err))
    );
});

// ────────────────────────────────────────
// Activate : nettoie les vieux caches
// ────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((k) => !k.startsWith(CACHE_VERSION))
                    .map((k) => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// ────────────────────────────────────────
// Fetch : stratégies de cache
// ────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    if (!request.url.startsWith(self.location.origin)) return;

    const url = new URL(request.url);

    // Bypass : Livewire, API, broadcasting, streaming, login/logout, Reverb
    if (url.pathname.startsWith('/livewire/')
     || url.pathname.startsWith('/api/')
     || url.pathname.startsWith('/broadcasting/')
     || url.pathname.startsWith('/assistant/stream')
     || url.pathname.startsWith('/app/')
     || url.pathname === '/login'
     || url.pathname === '/logout'
     || url.pathname.startsWith('/push/')) {
        return;
    }

    // HTML : network-first (toujours le frais quand connecté, fallback offline)
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithFallback(request));
    }
    // Assets statiques : cache-first
    else if (
        request.destination === 'style'
     || request.destination === 'script'
     || request.destination === 'font'
     || request.destination === 'image'
    ) {
        event.respondWith(cacheFirst(request));
    }
});

async function networkFirstWithFallback(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        if (cached) return cached;

        const offline = await caches.match('/offline.html');
        if (offline) return offline;

        return new Response(
            '<h1>Hors ligne</h1><p>Vérifie ta connexion.</p>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        return new Response('', { status: 504 });
    }
}

// ────────────────────────────────────────
// Push notifications
// ────────────────────────────────────────
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = {
            title: 'CleanUx',
            body: event.data.text(),
        };
    }

    const title = payload.title || 'CleanUx';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/badge-72.png',
        image: payload.image,
        tag: payload.tag,
        renotify: payload.renotify ?? true,
        requireInteraction: payload.requireInteraction ?? false,
        silent: payload.silent ?? false,
        vibrate: payload.vibrate || [100, 50, 100],
        data: {
            url: payload.url || '/',
            ...payload.data,
        },
        actions: payload.actions || [],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// ────────────────────────────────────────
// Click notification → focus tab ou ouvre URL
// ────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        (async () => {
            const allClients = await clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });

            for (const client of allClients) {
                if (client.url.startsWith(self.location.origin)) {
                    await client.focus();
                    if ('navigate' in client) {
                        return client.navigate(targetUrl);
                    }
                    client.postMessage({ type: 'navigate', url: targetUrl });
                    return;
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })()
    );
});

self.addEventListener('notificationclose', (event) => {
    // Hook éventuel pour tracker la fermeture sans clic
});
