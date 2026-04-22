<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Planning employé</h1>
            <p class="text-sm text-gray-500">Vue jour / semaine / mois, filtrée par zone, service et statut.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="previousPeriod" class="px-4 py-2 rounded-lg border text-sm">← Précédent</button>
            <button wire:click="goToday" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm">Aujourd’hui</button>
            <button wire:click="nextPeriod" class="px-4 py-2 rounded-lg border text-sm">Suivant →</button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <button wire:click="setViewMode('day')" class="px-3 py-2 rounded-lg text-sm {{ $viewMode === 'day' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">Jour</button>
                <button wire:click="setViewMode('week')" class="px-3 py-2 rounded-lg text-sm {{ $viewMode === 'week' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">Semaine</button>
                <button wire:click="setViewMode('month')" class="px-3 py-2 rounded-lg text-sm {{ $viewMode === 'month' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">Mois</button>
            </div>
            <div class="font-semibold text-gray-800">{{ $periodLabel }}</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="text-xs text-gray-500">Statut</label>
                <select wire:model.live="status" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    <option value="">Tous</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="refuse">Refusé</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Zone</label>
                <select wire:model.live="zoneId" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    <option value="">Toutes</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-gray-500">Service</label>
                <input wire:model.live.debounce.300ms="service" type="text" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" placeholder="Nettoyage, vitres, intervention...">
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border p-4"><p class="text-sm text-gray-500">Total</p><p class="text-2xl font-bold text-gray-900">{{ $planningStats['total'] }}</p></div>
        <div class="bg-white rounded-xl border p-4"><p class="text-sm text-gray-500">En attente</p><p class="text-2xl font-bold text-amber-600">{{ $planningStats['en_attente'] }}</p></div>
        <div class="bg-white rounded-xl border p-4"><p class="text-sm text-gray-500">En cours</p><p class="text-2xl font-bold text-blue-700">{{ $planningStats['en_cours'] }}</p></div>
        <div class="bg-white rounded-xl border p-4"><p class="text-sm text-gray-500">Terminées</p><p class="text-2xl font-bold text-emerald-700">{{ $planningStats['terminees'] }}</p></div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-4">
        @forelse($groupedMissions as $date => $missions)
            <div class="border rounded-2xl overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-800">{{ \Carbon\Carbon::parse($date)->translatedFormat('l d F Y') }}</h3>
                </div>
                <div class="divide-y">
                    @foreach($missions as $rdv)
                        <div class="p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <p class="font-semibold text-gray-900">{{ $rdv->heure }} — {{ $rdv->service_display_name }}</p>
                                    <x-badge :status="$rdv->status" />
                                    @if($rdv->serviceZone)
                                        <span class="px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-700">{{ $rdv->serviceZone->name }}</span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-700">{{ $rdv->client->name ?? 'Client' }} — {{ $rdv->adresse ?? 'Adresse non précisée' }}, {{ $rdv->ville ?? '—' }}</p>
                                @if($rdv->organizationSite)
                                    <p class="text-xs text-gray-500 mt-1">Site : {{ $rdv->organizationSite->name }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if($rdv->telephone_client)
                                    <a href="tel:{{ $rdv->telephone_client }}" class="px-3 py-2 rounded-lg bg-green-100 text-green-700 text-sm">Appeler</a>
                                @endif
                                @if($rdv->adresse || $rdv->ville)
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? '')) }}" target="_blank" class="px-3 py-2 rounded-lg bg-blue-100 text-blue-700 text-sm">Itinéraire</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center text-gray-500 italic py-8">Aucune mission sur cette période.</div>
        @endforelse
    </div>
</div>
