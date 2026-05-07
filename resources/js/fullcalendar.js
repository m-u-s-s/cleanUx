/**
 * Phase 6.1 — Wrapper Alpine pour FullCalendar v6 avec bridge Livewire.
 *
 * Architecture :
 *   1. cleanuxFC() exposé en global Alpine
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

window.cleanuxFC = function() {
    return {
        calendar: null,
        wire: null,
        lastDropInfo: null,

        init(livewireComponent) {
            this.wire = livewireComponent;
            const el = document.getElementById('cleanux-fullcalendar');
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
                        const events = await this.wire.fetchEvents(
                            info.startStr,
                            info.endStr
                        );
                        success(events);
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
                        await this.wire.handleEventDrop(bookingId, newStart);
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
                    this.wire.selectEvent(bookingId);
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
