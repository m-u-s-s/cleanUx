/**
 * Phase 8 — Gestion des subscriptions Web Push côté navigateur.
 *
 * Import dans resources/js/app.js (après pwa.js) :
 *   import './push-notifications';
 *
 * API publique :
 *   window.cleanuxPush.requestPermission()       → demande permission OS
 *   window.cleanuxPush.subscribe()               → souscrit + envoie au serveur
 *   window.cleanuxPush.unsubscribe()             → désinscrit
 *   window.cleanuxPush.getStatus()               → 'granted' | 'denied' | 'default' | 'unsupported'
 *   window.cleanuxPush.testNotification()        → notif test
 *   window.cleanuxPush.hasActiveSubscription()   → boolean
 */

(function () {
    if (typeof window === 'undefined') return;

    let publicKey = null;

    // ────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const output = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            output[i] = rawData.charCodeAt(i);
        }
        return output;
    }

    function arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function detectPlatform() {
        const ua = navigator.userAgent.toLowerCase();
        if (/android/.test(ua)) return 'android';
        if (/iphone|ipad|ipod/.test(ua)) return 'ios';
        return 'desktop';
    }

    function detectBrowser() {
        const ua = navigator.userAgent.toLowerCase();
        if (ua.includes('edg/')) return 'edge';
        if (ua.includes('firefox/')) return 'firefox';
        if (ua.includes('chrome/')) return 'chrome';
        if (ua.includes('safari/')) return 'safari';
        return 'other';
    }

    async function fetchPublicKey() {
        if (publicKey) return publicKey;

        try {
            const response = await fetch('/push/public-key', {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            publicKey = data.public_key;
            return publicKey;
        } catch (err) {
            console.warn('[Push] Failed to fetch VAPID public key', err);
            return null;
        }
    }

    // ────────────────────────────────────────
    // API publique
    // ────────────────────────────────────────

    const cleanuxPush = {
        isSupported() {
            return 'serviceWorker' in navigator
                && 'PushManager' in window
                && 'Notification' in window;
        },

        getStatus() {
            if (!this.isSupported()) return 'unsupported';
            return Notification.permission;
        },

        async requestPermission() {
            if (!this.isSupported()) return 'unsupported';
            const permission = await Notification.requestPermission();
            return permission;
        },

        async subscribe() {
            if (!this.isSupported()) {
                throw new Error('Push notifications not supported');
            }

            const permission = await this.requestPermission();
            if (permission !== 'granted') {
                throw new Error(`Permission denied: ${permission}`);
            }

            const key = await fetchPublicKey();
            if (!key) {
                throw new Error('VAPID public key not available — check server config');
            }

            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(key),
                });
            }

            const response = await fetch('/push/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
                        auth:   arrayBufferToBase64(subscription.getKey('auth')),
                    },
                    platform: detectPlatform(),
                    browser:  detectBrowser(),
                }),
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(`Subscribe failed: ${response.status} ${text}`);
            }

            return await response.json();
        },

        async unsubscribe() {
            if (!this.isSupported()) return false;

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (!subscription) return false;

            await fetch('/push/unsubscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            await subscription.unsubscribe();
            return true;
        },

        async testNotification() {
            const response = await fetch('/push/test', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            return await response.json();
        },

        async hasActiveSubscription() {
            if (!this.isSupported()) return false;
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                return subscription !== null;
            } catch (err) {
                return false;
            }
        },
    };

    window.cleanuxPush = cleanuxPush;
})();
