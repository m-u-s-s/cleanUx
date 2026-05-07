/**
 * Phase 8 — Enregistrement du service worker + UX d'installation PWA.
 *
 * Import dans resources/js/app.js :
 *   import './pwa';
 *
 * Comportements :
 *   1. Enregistre /sw.js au load
 *   2. Détecte l'événement 'beforeinstallprompt' (Chrome/Edge) → expose un
 *      bouton "Installer l'app" via dispatch d'event 'pwa:install-available'
 *   3. Écoute les messages du SW (notamment 'navigate' depuis click notif)
 *   4. Auto-update : si une nouvelle version du SW est dispo, recharge la page
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

                // Détection mise à jour du SW
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (!newWorker) return;

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Nouvelle version dispo → notifier l'utilisateur
                            window.dispatchEvent(new CustomEvent('pwa:update-available'));
                        }
                    });
                });
            } catch (err) {
                console.warn('[PWA] Échec enregistrement SW:', err);
            }
        });

        // ────────────────────────────────────────
        // 2. Réception des messages du SW
        //    (notamment navigate depuis click notification)
        // ────────────────────────────────────────
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'navigate' && event.data.url) {
                window.location.href = event.data.url;
            }
        });
    }

    // ────────────────────────────────────────
    // 3. Détection installation PWA (Chrome/Edge/Android)
    // ────────────────────────────────────────
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Notifie l'app qu'on peut proposer l'installation
        window.dispatchEvent(new CustomEvent('pwa:install-available'));
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        console.log('[PWA] App installée');
        window.dispatchEvent(new CustomEvent('pwa:installed'));
    });

    // Expose une fonction globale pour déclencher l'installation
    window.cleanuxPwa = {
        canInstall: () => deferredPrompt !== null,
        promptInstall: async () => {
            if (!deferredPrompt) return false;

            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            return outcome === 'accepted';
        },
        // Détection iOS Safari (qui ne supporte pas beforeinstallprompt)
        isIOS: () => /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
        isStandalone: () => window.matchMedia('(display-mode: standalone)').matches
                          || window.navigator.standalone === true,
    };
})();
