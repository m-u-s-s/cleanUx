/**
 * Phase 3 — Echo listeners globaux côté frontend.
 *
 * À importer dans resources/js/app.js après bootstrap.js :
 *
 *   import './bootstrap';
 *   import './echo-listeners';
 *
 * Ce fichier configure :
 *   • Notifications toast pour TaskAssigned (sur le channel user.{id})
 *   • Mise à jour du badge "online" via presence channels
 *   • Heartbeat de présence (touch toutes les 60s)
 *
 * Les composants Livewire ont leurs propres listeners via `#[On('echo-private:...')]`.
 */

(function () {
    if (typeof window === 'undefined' || !window.Echo) {
        // En SSR ou si Echo n'est pas chargé, ne rien faire.
        return;
    }

    // Récupère l'utilisateur courant depuis une meta tag (Laravel injecte avec
    // <meta name="user-id" content="{{ auth()->id() }}">).
    const userMeta = document.querySelector('meta[name="user-id"]');
    const orgMeta  = document.querySelector('meta[name="org-id"]');

    const userId = userMeta ? parseInt(userMeta.getAttribute('content'), 10) : null;
    const orgId  = orgMeta ? parseInt(orgMeta.getAttribute('content'), 10) : null;

    if (!userId) {
        return; // utilisateur non connecté
    }

    // ──────────────────────────────────────────────────────
    // 1) Channel personnel — notifications individuelles
    // ──────────────────────────────────────────────────────
    window.Echo.private(`user.${userId}`)
        .listen('.TaskAssigned', (payload) => {
            showToast({
                title: 'Nouvelle tâche',
                body:  `${payload.title}${payload.priority === 'urgent' ? ' (urgent)' : ''}`,
                type:  'info',
                action: {
                    label: 'Voir',
                    url:   '/team/tasks#task-' + payload.task_id,
                },
            });
            playSound('notification');
        })
        .listen('.UserPresenceChanged', (payload) => {
            // Self-update : n'affiche rien, met juste à jour l'UI si un widget de statut est visible
            window.dispatchEvent(new CustomEvent('cleanux:presence-self-changed', { detail: payload }));
        });

    // ──────────────────────────────────────────────────────
    // 2) Presence channel d'organisation
    // ──────────────────────────────────────────────────────
    if (orgId) {
        window.Echo.join(`presence-org.${orgId}`)
            .here((users) => {
                // Liste initiale des utilisateurs en ligne
                window.dispatchEvent(new CustomEvent('cleanux:presence-roster', {
                    detail: { users },
                }));
            })
            .joining((user) => {
                window.dispatchEvent(new CustomEvent('cleanux:presence-joined', {
                    detail: { user },
                }));
            })
            .leaving((user) => {
                window.dispatchEvent(new CustomEvent('cleanux:presence-left', {
                    detail: { user },
                }));
            })
            .listen('.UserPresenceChanged', (payload) => {
                window.dispatchEvent(new CustomEvent('cleanux:presence-status-changed', {
                    detail: payload,
                }));
            })
            .listen('.TaskAssigned', (payload) => {
                // Refresh du board partagé
                window.dispatchEvent(new CustomEvent('cleanux:task-assigned', { detail: payload }));
            })
            .listen('.TaskStatusChanged', (payload) => {
                window.dispatchEvent(new CustomEvent('cleanux:task-status-changed', { detail: payload }));
            })
            .error((err) => {
                console.warn('[CleanUx presence] auth error on org channel:', err);
            });
    }

    // ──────────────────────────────────────────────────────
    // 3) Heartbeat de présence
    // ──────────────────────────────────────────────────────
    // POST /presence/touch toutes les 60s pour maintenir le "last seen" du backend.
    let heartbeatTimer = null;

    function startHeartbeat() {
        if (heartbeatTimer) return;
        sendHeartbeat();
        heartbeatTimer = setInterval(sendHeartbeat, 60_000);
    }

    function stopHeartbeat() {
        if (heartbeatTimer) clearInterval(heartbeatTimer);
        heartbeatTimer = null;
    }

    function sendHeartbeat() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrf) return;

        fetch('/presence/touch', {
            method:  'POST',
            headers: {
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            credentials: 'same-origin',
        }).catch(() => {});
    }

    // Pause heartbeat quand l'onglet n'est plus visible
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopHeartbeat();
        } else {
            startHeartbeat();
        }
    });

    if (!document.hidden) {
        startHeartbeat();
    }

    // ──────────────────────────────────────────────────────
    // Helpers UI
    // ──────────────────────────────────────────────────────
    function showToast({ title, body, type = 'info', action = null }) {
        // Si le projet a déjà un système toast (ex: window.dispatchEvent toast),
        // l'utiliser. Sinon fallback notification navigateur.
        if (typeof window.cleanuxToast === 'function') {
            window.cleanuxToast({ title, body, type, action });
            return;
        }

        if ('Notification' in window && Notification.permission === 'granted') {
            const n = new Notification(title, { body });
            if (action?.url) {
                n.onclick = () => { window.location.href = action.url; };
            }
        }
    }

    function playSound(kind = 'notification') {
        // Optionnel : son discret. Met le fichier dans public/sounds/<kind>.mp3
        try {
            const audio = new Audio(`/sounds/${kind}.mp3`);
            audio.volume = 0.4;
            audio.play().catch(() => {});
        } catch (_) {}
    }
})();
