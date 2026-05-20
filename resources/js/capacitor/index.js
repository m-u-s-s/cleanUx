/**
 * Capacitor integration bridge — initialise les plugins natifs si l'app
 * tourne dans une wrap Capacitor (iOS/Android). No-op si on est en navigateur web.
 *
 * À importer dans `resources/js/app.js` si Capacitor est installé.
 */

let capacitorInitialized = false;

export async function initCapacitor() {
    if (capacitorInitialized) return;
    capacitorInitialized = true;

    // Detect if Capacitor is available
    if (typeof window === 'undefined' || ! window.Capacitor) {
        return;
    }

    try {
        const { App } = await import('@capacitor/app');

        // Deep linking : cleanux://bookings/123 → navigate within app
        App.addListener('appUrlOpen', (event) => {
            try {
                const url = new URL(event.url);
                const path = url.pathname + (url.search || '');
                if (path.startsWith('/bookings/') || path.startsWith('/dashboard/')) {
                    window.location.href = path;
                }
            } catch (e) {
                console.warn('appUrlOpen parse failed', e);
            }
        });

        App.addListener('appStateChange', (state) => {
            if (state.isActive) {
                // App came back to foreground — refresh data
                window.dispatchEvent(new CustomEvent('app:resumed'));
            }
        });
    } catch (e) {
        console.warn('Capacitor App plugin not available', e);
    }

    // Push notifications (FCM/APNs)
    try {
        const { PushNotifications } = await import('@capacitor/push-notifications');

        await PushNotifications.requestPermissions();
        await PushNotifications.register();

        PushNotifications.addListener('registration', async (token) => {
            // Envoie le device token au backend CleanUx
            try {
                await fetch('/api/client/devices/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        token: token.value,
                        platform: window.Capacitor.getPlatform(),  // ios | android
                        device_info: navigator.userAgent,
                    }),
                });
            } catch (e) {
                console.warn('device register fail', e);
            }
        });

        PushNotifications.addListener('pushNotificationReceived', (notification) => {
            window.dispatchEvent(new CustomEvent('push:received', { detail: notification }));
        });

        PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
            const data = action.notification?.data;
            if (data?.deep_link) {
                window.location.href = data.deep_link;
            }
        });
    } catch (e) {
        console.warn('Push notifications plugin not available', e);
    }

    // Geolocation background ping (provider en route)
    try {
        const { Geolocation } = await import('@capacitor/geolocation');
        window.cleanuxGeolocation = Geolocation;
    } catch (e) {
        console.warn('Geolocation plugin not available', e);
    }
}

// Auto-init quand le DOM est ready
if (typeof window !== 'undefined') {
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initCapacitor();
    } else {
        document.addEventListener('DOMContentLoaded', initCapacitor);
    }
}
