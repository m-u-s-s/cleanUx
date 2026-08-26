<div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-blue-900">📅 Calendrier interne</h2>
            <p class="text-sm text-gray-500">Vue consolidée admin avec filtres par zone, service, employé et statut.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="setPreset('today')" class="px-3 py-2 rounded-lg bg-white border text-sm">Aujourd’hui</button>
            <button wire:click="setPreset('week')" class="px-3 py-2 rounded-lg bg-white border text-sm">Semaine</button>
            <button wire:click="setPreset('month')" class="px-3 py-2 rounded-lg bg-white border text-sm">Mois</button>
            <button wire:click="resetFilters" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm">Réinitialiser</button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-2xl shadow border"><p class="text-sm text-gray-500">Total</p><p class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p></div>
        <div class="bg-white p-4 rounded-2xl shadow border"><p class="text-sm text-gray-500">En attente</p><p class="text-2xl font-bold text-amber-600">{{ $stats['en_attente'] }}</p></div>
        <div class="bg-white p-4 rounded-2xl shadow border"><p class="text-sm text-gray-500">Confirmés</p><p class="text-2xl font-bold text-emerald-600">{{ $stats['confirme'] }}</p></div>
        <div class="bg-white p-4 rounded-2xl shadow border"><p class="text-sm text-gray-500">Terrain</p><p class="text-2xl font-bold text-blue-600">{{ $stats['terrain'] }}</p></div>
        <div class="bg-white p-4 rounded-2xl shadow border"><p class="text-sm text-gray-500">Terminés</p><p class="text-2xl font-bold text-slate-900">{{ $stats['termine'] }}</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="dateFrom">Du</label>
                <input id="dateFrom" type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-gray-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="dateTo">Au</label>
                <input id="dateTo" type="date" wire:model.live="dateTo" class="w-full rounded-lg border-gray-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="status">Statut</label>
                <select id="status" wire:model.live="status" class="w-full rounded-lg border-gray-300 shadow-sm">
                    <option value="">— Tous —</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="refuse">Refusé</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="zoneId">Zone</label>
                <select id="zoneId" wire:model.live="zoneId" class="w-full rounded-lg border-gray-300 shadow-sm">
                    <option value="">— Toutes —</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="serviceId">Service</label>
                <select id="serviceId" wire:model.live="serviceId" class="w-full rounded-lg border-gray-300 shadow-sm">
                    <option value="">— Tous —</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="employeId">Employé</label>
                <select id="employeId" wire:model.live="employeId" class="w-full rounded-lg border-gray-300 shadow-sm">
                    <option value="">— Tous —</option>
                    @foreach($employes as $employe)
                        <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="search">Recherche</label>
                <input id="search" type="text" wire:model.live.debounce.300ms="search" placeholder="Référence, adresse, client, employé..." class="w-full rounded-lg border-gray-300 shadow-sm">
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-2xl shadow border p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-slate-800">Vue calendrier</h3>
                <select wire:model.live="viewMode" class="rounded-lg border-gray-300 text-sm shadow-sm">
                    <option value="dayGridMonth">Mois</option>
                    <option value="timeGridWeek">Semaine</option>
                    <option value="timeGridDay">Jour</option>
                    <option value="listWeek">Liste</option>
                </select>
            </div>

            <div wire:ignore class="overflow-x-auto">
                {{-- min-w pour que la grille mensuelle FullCalendar reste lisible et
                     scrolle horizontalement sur mobile au lieu de cliper ses cellules. --}}
                <div id="admin-internal-calendar" class="min-h-[650px] min-w-[560px]"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border p-4 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-800">À venir</h3>
                <a href="{{ route('admin.calendar.settings') }}" class="text-sm text-blue-700 font-medium">Paramètres Google</a>
            </div>
            <div class="space-y-3 max-h-[650px] overflow-auto">
                @forelse($upcoming as $rdv)
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $rdv->service_display_name }}</p>
                                <p class="text-sm text-slate-500">{{ $rdv->client?->name ?? 'Client' }} • {{ $rdv->employe?->name ?? 'Non assigné' }}</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-700">{{ $rdv->status }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">{{ $rdv->date?->format('d/m/Y') }} à {{ substr((string) $rdv->heure, 0, 5) }}</p>
                        <p class="text-sm text-slate-500">{{ $rdv->serviceZone?->name ?? 'Zone non définie' }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ trim(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? '')) }}</p>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Aucun rendez-vous dans la plage actuelle.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{--
        LE DÉTAIL D'UN ÉVÉNEMENT — une fiche, pas la boîte du navigateur.

        `alert()` empilait six lignes « Clé : valeur » dans une fenêtre système : sans
        hiérarchie, sans thème, illisible sur un téléphone, et bloquant le fil tant qu'on ne
        l'avait pas fermée. Un détail se PARCOURT ; une alerte s'interrompt.

        Les six champs sont posés en cases plutôt qu'en lignes, comme les écrans terrain :
        l'œil les balaye au lieu de les lire.
    --}}
    <div x-data="{ ouvert: false, champs: [] }"
         x-on:brio-evenement.window="champs = $event.detail.champs; ouvert = true"
         x-on:keydown.escape.window="ouvert = false">
        <template x-if="ouvert">
            <div class="brio-modal-fond grid place-items-center p-4" x-on:click.self="ouvert = false">
                <div class="brio-modal" role="dialog" aria-modal="true" aria-labelledby="titre-evenement">
                    <h2 id="titre-evenement" class="brio-modal-titre">{{ __('Détail de l’intervention') }}</h2>

                    <dl class="brio-terrain mt-3">
                        <template x-for="champ in champs" :key="champ.libelle">
                            <div class="brio-terrain-case">
                                <dt class="brio-terrain-tete" x-text="champ.libelle"></dt>
                                <dd class="brio-terrain-valeur" x-text="champ.valeur"></dd>
                            </div>
                        </template>
                    </dl>

                    <div class="brio-modal-actions">
                        <button type="button" x-init="$el.focus()" class="brio-btn brio-btn-nu"
                                x-on:click="ouvert = false">{{ __('Fermer') }}</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script>
            document.addEventListener('livewire:navigated', initInternalCalendar);
            document.addEventListener('livewire:load', initInternalCalendar);

            let brioInternalCalendar = null;

            function initInternalCalendar() {
                const el = document.getElementById('admin-internal-calendar');
                if (!el) return;

                const events = @js($calendarEvents);
                const initialView = @js($viewMode);

                if (brioInternalCalendar) {
                    brioInternalCalendar.destroy();
                }

                brioInternalCalendar = new FullCalendar.Calendar(el, {
                    initialView: initialView || 'dayGridMonth',
                    height: 650,
                    locale: 'fr',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    events: events,
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    eventClick: function(info) {
                        const p = info.event.extendedProps || {};

                        window.dispatchEvent(new CustomEvent('brio-evenement', {
                            detail: {
                                champs: [
                                    { libelle: 'Client', valeur: p.client || '—' },
                                    { libelle: 'Employé', valeur: p.employe || '—' },
                                    { libelle: 'Zone', valeur: p.zone || '—' },
                                    { libelle: 'Statut', valeur: p.status || '—' },
                                    { libelle: 'Adresse', valeur: p.adresse || '—' },
                                    { libelle: 'Référence', valeur: p.reference || '—' },
                                ],
                            },
                        }));
                    }
                });

                brioInternalCalendar.render();
            }
        </script>
    @endpush
</div>
