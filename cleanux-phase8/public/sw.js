/**
 * Phase 8 — Service Worker CleanUx PWA
 *
 * Responsabilités :
 *   1. Cache des assets statiques (CSS, JS, fonts, icons) → mode offline
 *   2. Réception des push notifications (Web Push API)
 *   3. Click sur notification → focus tab existant ou ouverture URL
 *   4. Fallback offline.html quand pas de réseau et page non cachée
 *
 * IMPORTANT :
 *   - Doit être servi depuis la racine (/sw.js) pour avoir scope="/"
 *   - Doit être en HTTPS en prod (sauf localhost en dev)
 *   - Bump CACHE_VERSION pour forcer un re-cache après MAJ
 */

const CACHE_VERSION = 'cleanux-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

// Assets à pré-cacher au installation. On reste minimaliste : Vite génère des
// noms hashés qu'on ne peut pas tous lister. Le runtime cache s'occupera du reste.
const PRECACHE_URLS = [
    '/',
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.ico',
];

// ────────────────────────────────────────
// Install : pré-cache des assets critiques
// ────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()) // active immédiatement
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

    // Pas de cache pour les requêtes non-GET ni cross-origin
    if (request.method !== 'GET') return;
    if (!request.url.startsWith(self.location.origin)) return;

    const url = new URL(request.url);

    // Pas de cache pour API/Livewire/streaming/Reverb
    if (url.pathname.startsWith('/livewire/')
     || url.pathname.startsWith('/api/')
     || url.pathname.startsWith('/broadcasting/')
     || url.pathname.startsWith('/assistant/stream')
     || url.pathname.startsWith('/app/')        // Reverb
     || url.pathname === '/login'
     || url.pathname === '/logout') {
        return;
    }

    // Stratégies par type :
    //  - HTML : network-first (toujours le frais quand connecté, fallback cache offline)
    //  - Assets statiques (CSS/JS/img/font) : cache-first (perf)
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithFallback(request));
    } else if (
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
        // Met à jour le runtime cache
        if (response.ok) {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Offline → essaye le cache runtime puis fallback offline.html
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
        tag: payload.tag,                  // évite empilage de notifs identiques
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

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ────────────────────────────────────────
// Click sur notification
// ────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        (async () => {
            // Si un onglet CleanUx est déjà ouvert, on le focus
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
                    // Sinon postMessage pour navigation côté client
                    client.postMessage({ type: 'navigate', url: targetUrl });
                    return;
                }
            }

            // Aucun onglet ouvert → ouvrir nouveau
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })()
    );
});

// ────────────────────────────────────────
// Notification fermée (pour analytics éventuels)
// ────────────────────────────────────────
self.addEventListener('notificationclose', (event) => {
    // Hook éventuel pour tracker la fermeture sans clic
});
