/**
 * Phase 6.1 — Wrapper Alpine pour FullCalendar v6 avec bridge Livewire.
 *
 * Architecture :
 *   1. brioFC() exposé en global Alpine
 *   2. init($wire) appelé dans x-init de la blade
 *   3. Crée une instance Calendar avec callbacks :
 *      - events : fetch via $wire.fetchEvents()
 *      - eventDrop : appelle $wire.handleEventDrop() puis Livewire revert si erreur
 *      - eventClick : appelle $wire.selectEvent()
 *   4. Listen aux events Livewire :
 *      - calendar:refresh → calendar.refetchEvents()
 *      - calendar:revert  → revert le drag (info.revert())
 *
 * Import dans resources/js/app.js :
 *   import './fullcalendar';
 */

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import frLocale from '@fullcalendar/core/locales/fr';
import nlLocale from '@fullcalendar/core/locales/nl';

window.brioFC = function() {
    return {
        calendar: null,
        lastDropInfo: null,
        nom: null,

        /*
         * LE COMPOSANT SE RESOUT PAR SON NOM, A CHAQUE APPEL.
         *
         * Quatre tentatives ont echoue avant celle-ci, et chacune apprend quelque chose :
         *   - la magie `$wire` d'Alpine rend une fonction asynchrone sur un noeud `wire:ignore` ;
         *   - remonter l'arbre tombe sur le composant de la barre de navigation ;
         *   - un mandataire CAPTURE au montage devient perime des que Livewire rehydrate ;
         *   - et `typeof $wire.uneMethode === 'function'` est TOUJOURS vrai : le mandataire
         *     Livewire repond a n'importe quel nom, le serveur seul sait s'il existe. Le
         *     test de capacite ne discriminait donc rien, d'ou le 500
         *     « Public method [fetchEvents] not found on component ».
         *
         * Le nom vient de la vue, ou il est connu sans ambiguite.
         */
        composant() {
            if (!window.Livewire || !this.nom) return null;

            const porteur = window.Livewire.all().find((c) => c.name === this.nom);

            return porteur ? porteur.$wire : null;
        },

        /*
         * `demarrer`, PAS `init`.
         *
         * Alpine appelle TOUT SEUL la methode `init()` d'un `x-data`, sans argument — puis
         * `x-init="init($wire)"` l'appelait une seconde fois. Le premier passage posait donc
         * `this.wire = undefined`, montait un calendrier, et sa premiere requete d'evenements
         * echouait avant meme que le second passage n'arrive. Un nom qu'Alpine ne connait pas
         * supprime le premier passage.
         */
        demarrer(nomDuComposant) {
            if (this.calendar) return; // Un second appel ne monte pas un second calendrier.

            this.nom = nomDuComposant;

            const el = document.getElementById('brio-fullcalendar');
            if (!el) return;

            // Détecter la locale courante (Phase 9)
            const locale = document.documentElement.lang || 'fr';

            this.calendar = new Calendar(el, {
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                initialView: 'dayGridMonth',
                locales: [frLocale, nlLocale],
                locale: locale,
                firstDay: 1, // lundi
                height: 'auto',
                contentHeight: 600,
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,timeGridWeek,listWeek',
                },
                buttonText: {
                    today: locale === 'fr' ? "Aujourd'hui" : (locale === 'nl' ? 'Vandaag' : 'Today'),
                    month: locale === 'fr' ? 'Mois' : (locale === 'nl' ? 'Maand' : 'Month'),
                    week:  locale === 'fr' ? 'Semaine' : (locale === 'nl' ? 'Week' : 'Week'),
                    list:  locale === 'fr' ? 'Liste' : (locale === 'nl' ? 'Lijst' : 'List'),
                },
                editable: true,           // active drag-drop globalement
                droppable: false,
                eventStartEditable: true,
                eventDurationEditable: false,
                dayMaxEventRows: 4,
                navLinks: true,
                weekNumbers: false,
                nowIndicator: true,

                // ─── Chargement events depuis Livewire ───
                events: async (info, success, failure) => {
                    try {
                        const wire = this.composant();

                        if (!wire) {
                            // Livewire n'a pas fini de monter : le calendrier redemandera.
                            success([]);

                            return;
                        }

                        success(await wire.fetchEvents(info.startStr, info.endStr));
                    } catch (err) {
                        console.error('Failed to fetch events', err);
                        failure(err);
                    }
                },

                // ─── Drag-and-drop ───
                eventDrop: async (info) => {
                    this.lastDropInfo = info;
                    const bookingId = parseInt(info.event.id, 10);
                    const newStart = info.event.start.toISOString();

                    try {
                        await this.composant()?.handleEventDrop(bookingId, newStart);
                        // pas de revert auto — Livewire dispatch 'calendar:refresh' si OK
                        // ou 'calendar:revert' si erreur (handlers ci-dessous).
                    } catch (err) {
                        console.error('Drop failed', err);
                        info.revert();
                    }
                },

                // ─── Clic sur un event ───
                eventClick: (info) => {
                    const bookingId = parseInt(info.event.id, 10);
                    this.composant()?.selectEvent(bookingId);
                    info.jsEvent.preventDefault();
                },

                // Style des events non-éditables (status final)
                eventDidMount: (info) => {
                    if (info.event.extendedProps.status === 'termine'
                        || info.event.extendedProps.status === 'completed'
                        || info.event.extendedProps.status === 'annule'
                        || info.event.extendedProps.status === 'cancelled') {
                        info.el.style.opacity = '0.6';
                    }
                    // Tooltip basique
                    const ref = info.event.extendedProps.reference || '';
                    const site = info.event.extendedProps.site_name || '';
                    info.el.title = `${info.event.title}${site ? ' — ' + site : ''}${ref ? ' (' + ref + ')' : ''}`;
                },
            });

            this.calendar.render();

            // ─── Bind aux events Livewire ───
            if (typeof Livewire !== 'undefined') {
                Livewire.on('calendar:refresh', () => {
                    if (this.calendar) {
                        this.calendar.refetchEvents();
                    }
                });

                Livewire.on('calendar:revert', () => {
                    if (this.lastDropInfo) {
                        this.lastDropInfo.revert();
                        this.lastDropInfo = null;
                    }
                });
            }
        },
    };
};
