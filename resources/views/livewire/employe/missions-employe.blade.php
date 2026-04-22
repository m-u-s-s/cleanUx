<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-blue-900">📋 Mes missions</h2>
                <p class="text-sm text-gray-500">Suivi opérationnel de vos rendez-vous et interventions.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('employe.dashboard') }}"
                    class="inline-flex items-center px-4 py-2 rounded-xl border bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    ← Dashboard
                </a>
                <a href="{{ route('employe.historique') }}"
                    class="inline-flex items-center px-4 py-2 rounded-xl border bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    🕘 Historique
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['total'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">À confirmer</p>
                <p class="text-2xl font-bold text-amber-600">{{ $stats['a_confirmer'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">À faire</p>
                <p class="text-2xl font-bold text-blue-700">{{ $stats['a_faire'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">Terminées</p>
                <p class="text-2xl font-bold text-emerald-700">{{ $stats['terminees'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">Zones</p>
                <p class="text-2xl font-bold text-indigo-700">{{ $stats['zone_count'] ?? 0 }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)] gap-6 items-start">
            <div class="space-y-6">
                @include('livewire.employe.mes-rendez-vous')
            </div>

            <div class="space-y-4">
                @if($selectedRendezVous && $selectedMission)
                    <div class="bg-white rounded-2xl border border-indigo-200 shadow-sm p-5 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Mission sélectionnée</p>
                                <h3 class="text-lg font-bold text-slate-900">
                                    {{ $selectedRendezVous->service_display_name }}
                                </h3>
                                <p class="text-sm text-slate-500">
                                    RDV #{{ $selectedRendezVous->id }} · Mission #{{ $selectedMission->id }}
                                </p>
                            </div>

                            <button
                                wire:click="clearSelectedRdv"
                                class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Fermer
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="text-slate-500">Client</p>
                                <p class="font-semibold text-slate-900">{{ $selectedRendezVous->client?->name ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="text-slate-500">Adresse</p>
                                <p class="font-semibold text-slate-900">{{ $selectedRendezVous->adresse ?? '—' }}, {{ $selectedRendezVous->ville ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="text-slate-500">Statut RDV</p>
                                <p class="font-semibold text-slate-900">{{ $selectedRendezVous->status }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="text-slate-500">Statut mission</p>
                                <p class="font-semibold text-slate-900">{{ $selectedMission->status }}</p>
                            </div>
                        </div>
                    </div>

                    <livewire:employe.mission-actions :mission="$selectedMission" :key="'mission-actions-'.$selectedMission->id" />

                    @if(in_array($selectedMission->status, ['en_route', 'arrived', 'started', 'paused']))
                        <livewire:employe.mission-route-tracking :mission="$selectedMission" :key="'mission-route-'.$selectedMission->id" />
                    @endif

                    @if(in_array($selectedMission->status, ['arrived', 'started', 'paused', 'completed']))
                        <livewire:employe.mission-execution-board :mission="$selectedMission" :key="'mission-execution-'.$selectedMission->id" />
                    @endif

                    <livewire:employe.mission-incident-board :mission="$selectedMission" :key="'incident-board-'.$selectedMission->id" />
                @else
                    <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500">
                        <p class="text-base font-semibold text-slate-700">Aucune mission ouverte</p>
                        <p class="mt-2 text-sm">
                            Sélectionnez un rendez-vous avec une mission liée pour afficher le panneau terrain,
                            le tracking, les actions et les incidents.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
