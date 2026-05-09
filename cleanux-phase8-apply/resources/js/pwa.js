/**
 * Phase 8 — Enregistrement du service worker + UX d'installation PWA.
 *
 * Import dans resources/js/app.js :
 *   import './pwa';
 */

(function () {
    if (typeof window === 'undefined') return;

    // ────────────────────────────────────────
    // 1. Enregistrement du Service Worker
    // ────────────────────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/',
                });

                console.log('[PWA] Service Worker enregistré:', registration.scope);

                // Auto-reload quand un nouveau SW prend le contrôle
                let refreshing = false;
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    if (refreshing) return;
                    refreshing = true;
                    window.location.reload();
                });

                // Détection MAJ du SW
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (!newWorker) return;

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            window.dispatchEvent(new CustomEvent('pwa:update-available'));
                        }
                    });
                });
            } catch (err) {
                console.warn('[PWA] Échec enregistrement SW:', err);
            }
        });

        // Réception messages du SW (notamment navigate depuis click notif)
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'navigate' && event.data.url) {
                window.location.href = event.data.url;
            }
        });
    }

    // ────────────────────────────────────────
    // 2. Détection installation PWA (Chrome/Edge/Android)
    // ────────────────────────────────────────
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        window.dispatchEvent(new CustomEvent('pwa:install-available'));
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        console.log('[PWA] App installée');
        window.dispatchEvent(new CustomEvent('pwa:installed'));
    });

    // API globale pour déclencher l'installation
    window.cleanuxPwa = {
        canInstall: () => deferredPrompt !== null,
        promptInstall: async () => {
            if (!deferredPrompt) return false;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            return outcome === 'accepted';
        },
        isIOS: () => /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
        isStandalone: () => window.matchMedia('(display-mode: standalone)').matches
                          || window.navigator.standalone === true,
    };
})();
