/**
 * Phase 5.2 — Consumer EventSource pour streaming chatbot.
 *
 * À importer dans resources/js/app.js après bootstrap.js :
 *   import './assistant-streaming';
 *
 * Architecture :
 *   1. AssistantWidget Livewire dispatche 'assistant:stream-start' avec
 *      { url: signedUrl, conversation_id, user_message_id }
 *   2. Ce module ouvre un EventSource sur l'URL signée
 *   3. À chaque text_delta, on append du texte à un container DOM stable
 *      (pas de Livewire round-trip pour la fluidité)
 *   4. Sur 'persisted' ou 'stop', on dispatch 'assistant:stream-completed'
 *      vers Livewire pour qu'il reload la liste depuis la DB
 *   5. Sur 'error', on dispatch 'assistant:stream-error'
 *
 * Le container DOM cible est identifié par data-streaming-target (rendu par la blade).
 */

(function () {
    if (typeof window === 'undefined') return;

    let currentEventSource = null;
    let currentTarget      = null;

    /**
     * Démarre un nouveau stream.
     */
    function startStream(detail) {
        // Si un stream précédent tourne encore, le couper.
        abortStream();

        const { url, conversation_id, user_message_id } = detail;

        currentTarget = document.querySelector('[data-streaming-target]');
        if (currentTarget) {
            currentTarget.textContent = '';
            currentTarget.dataset.streaming = 'true';
        }

        const es = new EventSource(url);
        currentEventSource = es;

        // ──────────────────────────────────────────────────────
        // text_delta : append au container
        // ──────────────────────────────────────────────────────
        es.addEventListener('text_delta', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (currentTarget && data.text) {
                    currentTarget.textContent += data.text;
                    autoScroll();
                }
            } catch (_) {}
        });

        // ──────────────────────────────────────────────────────
        // tool_use_start : afficher un badge "🔧 Le bot utilise X..."
        // ──────────────────────────────────────────────────────
        es.addEventListener('tool_use_start', (e) => {
            try {
                const data = JSON.parse(e.data);
                appendToolBadge(data.tool_name);
            } catch (_) {}
        });

        // ──────────────────────────────────────────────────────
        // start : init
        // ──────────────────────────────────────────────────────
        es.addEventListener('start', (e) => {
            // peut afficher le model utilisé en mode debug si besoin
        });

        // ──────────────────────────────────────────────────────
        // persisted : message final enregistré côté serveur
        // ──────────────────────────────────────────────────────
        es.addEventListener('persisted', (e) => {
            try {
                const data = JSON.parse(e.data);
                completeStream({
                    message_id: data.message_id,
                    has_tools:  data.has_tools,
                });
            } catch (_) {
                completeStream({});
            }
        });

        // ──────────────────────────────────────────────────────
        // stop : fin du stream
        // ──────────────────────────────────────────────────────
        es.addEventListener('stop', () => {
            // Si pas reçu 'persisted' avant, on déclenche le complete quand même
            // (cas où le serveur a stop avant de persister)
            setTimeout(() => completeStream({}), 50);
        });

        // ──────────────────────────────────────────────────────
        // error : erreur métier (envoyée par le serveur dans le payload)
        // ──────────────────────────────────────────────────────
        es.addEventListener('error_event', (e) => {
            try {
                const data = JSON.parse(e.data);
                errorStream(data.message || 'Erreur inconnue');
            } catch (_) {
                errorStream('Erreur de stream');
            }
        });

        // ──────────────────────────────────────────────────────
        // EventSource connection error (réseau)
        // ──────────────────────────────────────────────────────
        es.onerror = () => {
            // EventSource déclenche `onerror` aussi quand le serveur ferme
            // proprement la connexion. On distingue via readyState.
            if (es.readyState === EventSource.CLOSED) {
                // Stream fermé par le serveur — vérifier qu'on a bien complété.
                // Si on avait déjà reçu 'persisted', completeStream a été appelé.
                // Sinon c'est probablement un timeout/coupure → error.
                if (currentTarget && currentTarget.dataset.streaming === 'true') {
                    errorStream('Connexion interrompue');
                }
            }
        };
    }

    function appendToolBadge(toolName) {
        if (!currentTarget) return;
        const friendly = friendlyToolName(toolName);
        const span = document.createElement('div');
        span.className = 'mt-1 inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700';
        span.innerHTML = `🔧 <span>${escapeHtml(friendly)}</span>`;
        currentTarget.parentElement?.appendChild(span);
        autoScroll();
    }

    function friendlyToolName(name) {
        const map = {
            list_my_bookings:      'Consultation de tes réservations',
            list_my_sites:         'Consultation de tes locaux',
            list_services_catalog: 'Catalogue de services',
            create_booking:        'Préparation d\'une réservation',
            cancel_booking:        'Préparation d\'une annulation',
            get_invoice:           'Lecture d\'une facture',
            register_site:         'Enregistrement d\'un nouveau site',
            report_issue:          'Signalement d\'un incident',
        };
        return map[name] || name;
    }

    function completeStream(detail) {
        if (currentTarget) {
            currentTarget.dataset.streaming = 'false';
        }

        if (currentEventSource) {
            currentEventSource.close();
            currentEventSource = null;
        }

        // Notifie Livewire pour reload de la liste persistée
        if (typeof Livewire !== 'undefined') {
            Livewire.dispatch('assistant:stream-completed', detail);
        }
    }

    function errorStream(message) {
        if (currentEventSource) {
            currentEventSource.close();
            currentEventSource = null;
        }
        if (currentTarget) {
            currentTarget.dataset.streaming = 'false';
        }
        if (typeof Livewire !== 'undefined') {
            Livewire.dispatch('assistant:stream-error', { message });
        }
    }

    function abortStream() {
        if (currentEventSource) {
            currentEventSource.close();
            currentEventSource = null;
        }
        currentTarget = null;
    }

    function autoScroll() {
        const container = document.getElementById('chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ──────────────────────────────────────────────────────
    // Bind sur les events Livewire
    // ──────────────────────────────────────────────────────
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('assistant:stream-start', (event) => {
            // Livewire 3 peut wrapper l'event dans un array
            const detail = Array.isArray(event) ? event[0] : event;
            startStream(detail);
        });
    });

    // Fallback pour les events DOM custom
    window.addEventListener('assistant:stream-start', (e) => {
        startStream(e.detail || {});
    });

    // Expose pour debug manuel
    window.cleanuxAssistantStream = {
        start: startStream,
        abort: abortStream,
    };
})();
