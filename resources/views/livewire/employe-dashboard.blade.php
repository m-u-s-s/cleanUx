@php
$statsJour = $statsJour ?? [
'total' => 0,
'a_faire' => 0,
'en_cours' => 0,
'terminees' => 0,
'refusees' => 0,
];

$missionsDuJour = $missionsDuJour ?? collect();
$historiqueRecent = $historiqueRecent ?? collect();
$prochaineMission = $prochaineMission ?? null;
@endphp

<div class="space-y-6">
    <x-active-sessions />

    <x-page-shell
        eyebrow="Portail employé"
        title="Ma journée"
        subtitle="Vue rapide de vos missions, actions prioritaires et historique récent.">
        <x-slot name="actions">
            <x-ui.badge :label="$statsJour['total'] . ' mission(s) aujourd’hui'" tone="blue" icon="📅" />
            <x-ui.badge :label="$statsJour['terminees'] . ' terminée(s)'" tone="green" icon="✅" />
            <x-ui.action-button :href="route('employe.missions')" icon="📋">Toutes mes missions</x-ui.action-button>
            <x-ui.action-button :href="route('employe.historique')" icon="🕘">Mon historique</x-ui.action-button>
        </x-slot>
    </x-page-shell>

    @if(auth()->user()->canReceiveStripeConnectPayments())
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
        Votre compte de paiement est actif. Vous pouvez recevoir vos reversements.
    </div>
    @else
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
        <p class="font-semibold">Paiement prestataire non configuré</p>
        <p class="text-sm mt-1">
            Configurez Stripe Connect pour recevoir automatiquement vos paiements.
        </p>

        <a
            href="{{ route('employe.stripe-connect.start') }}"
            class="inline-flex mt-3 rounded-xl bg-slate-900 px-4 py-2 text-white">
            Configurer mes paiements
        </a>
    </div>
    @endif
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
        <x-ui.stat title="Total" :value="$statsJour['total']" tone="slate" icon="📦" hint="Toutes les missions du jour" />
        <x-ui.stat title="À faire" :value="$statsJour['a_faire']" tone="amber" icon="⏳" hint="Missions encore à démarrer" />
        <x-ui.stat title="En cours" :value="$statsJour['en_cours']" tone="blue" icon="🚚" hint="Missions déjà lancées" />
        <x-ui.stat title="Terminées" :value="$statsJour['terminees']" tone="green" icon="✅" hint="Missions clôturées" />
        <x-ui.stat title="Refusées" :value="$statsJour['refusees']" tone="red" icon="⛔" hint="Missions non exécutées" />
    </div>

    @if($prochaineMission)
    <div class="overflow-hidden rounded-[28px] bg-gradient-to-r from-blue-700 via-sky-700 to-indigo-700 p-6 text-white shadow-[0_18px_50px_rgba(37,99,235,0.22)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm text-blue-100">Prochaine mission</p>
                <h3 class="mt-1 text-xl font-bold">
                    {{ $prochaineMission->service_display_name ?: 'Service non précisé' }}
                </h3>
                <p class="mt-1 text-sm text-blue-100">{{ $prochaineMission->date }} à {{ $prochaineMission->heure }}</p>
                <p class="text-sm text-blue-100">{{ $prochaineMission->client->name ?? 'Client' }} • {{ $prochaineMission->adresse ?? 'Adresse non précisée' }}, {{ $prochaineMission->ville ?? '—' }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if($prochaineMission->telephone_client)
                <a href="tel:{{ $prochaineMission->telephone_client }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">📞 Appeler</a>
                @endif

                @if($prochaineMission->adresse || $prochaineMission->ville)
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($prochaineMission->adresse ?? '') . ' ' . ($prochaineMission->ville ?? '')) }}" target="_blank" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">📍 GPS</a>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card padding="p-5" title="Missions du jour" subtitle="Triées par priorité d’exécution." eyebrow="Aujourd’hui">
                <div class="space-y-4">
                    @forelse($missionsDuJour as $rdv)
                    <div class="cu-list-item {{ $rdv->status === 'sur_place' ? 'ring-2 ring-indigo-200 border-indigo-300' : '' }}">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h4 class="text-lg font-semibold text-slate-900">
                                    {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                </h4>
                                <p class="text-sm text-slate-600">👤 {{ $rdv->client->name ?? 'Client' }}</p>
                                <p class="text-sm text-slate-600">🕒 {{ $rdv->heure }} • 📍 {{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :status="$rdv->status" />
                                <x-priority-badge :priority="$rdv->priorite" />
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-2">
                            <div class="space-y-1">
                                <p><span class="font-medium">Téléphone :</span> {{ $rdv->telephone_client ?? '—' }}</p>
                                <p><span class="font-medium">Durée estimée :</span> {{ $rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—' }}</p>
                                <p><span class="font-medium">Type de lieu :</span> {{ ucfirst($rdv->type_lieu ?? '—') }}</p>
                            </div>

                            <div class="space-y-1">
                                <p><span class="font-medium">Surface :</span> {{ $rdv->surface ?? '—' }}</p>
                                <p><span class="font-medium">Parking :</span> {{ $rdv->acces_parking ? 'Oui' : 'Non' }}</p>
                                <p><span class="font-medium">Animaux :</span> {{ $rdv->presence_animaux ? 'Oui' : 'Non' }}</p>
                            </div>
                        </div>

                        @if($rdv->commentaire_client)
                        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                            <span class="font-medium">Remarque client :</span>
                            {{ $rdv->commentaire_client }}
                        </div>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            @if($rdv->telephone_client)
                            <a href="tel:{{ $rdv->telephone_client }}" class="inline-flex items-center rounded-xl bg-green-100 px-3 py-2 text-sm font-medium text-green-700 transition hover:bg-green-200">📞 Appeler</a>
                            @endif

                            @if($rdv->adresse || $rdv->ville)
                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? '')) }}" target="_blank" class="inline-flex items-center rounded-xl bg-blue-100 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-200">📍 GPS</a>
                            @endif
                        </div>
                    </div>
                    @empty
                    <x-ui.empty-state title="Aucune mission aujourd’hui" message="Les nouvelles missions assignées apparaîtront ici automatiquement." icon="🗓️" />
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card padding="p-5" title="Gestion complète des missions" subtitle="Suivi opérationnel, changement de statut et actions terrain." eyebrow="Terrain">
                <livewire:employe.mes-rendez-vous />
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card padding="p-5" title="Historique récent" subtitle="Vos dernières missions terminées et leur rythme d’exécution." eyebrow="Suivi">
                <div class="space-y-3">
                    @forelse($historiqueRecent as $rdv)
                    <div class="cu-list-item">
                        <p class="font-medium text-slate-900">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                        <p class="text-sm text-slate-600">{{ $rdv->date }} à {{ $rdv->heure }}</p>
                        <p class="text-sm text-slate-600">{{ $rdv->client->name ?? 'Client' }}</p>
                        @if($rdv->duree_reelle)
                        <p class="mt-1 text-xs text-slate-500">Durée réelle : {{ $rdv->duree_reelle }} min</p>
                        @endif
                    </div>
                    @empty
                    <x-ui.empty-state title="Aucun historique récent" message="Votre historique de missions terminées apparaîtra ici." icon="🧾" />
                    @endforelse
                </div>
            </x-ui.card>


            <x-ui.card padding="p-5" title="Zones assignées" subtitle="Vos zones de couverture actives et les éventuels écarts du jour." eyebrow="Couverture">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2">Zones actives</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse($assignedZones as $zone)
                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $zone->name }}</span>
                            @empty
                            <span class="text-sm text-slate-500">Aucune zone assignée.</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-2xl border {{ $missionsHorsZone->isNotEmpty() ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold {{ $missionsHorsZone->isNotEmpty() ? 'text-red-700' : 'text-emerald-700' }}">Mission(s) hors zone</h4>
                                <p class="mt-1 text-sm {{ $missionsHorsZone->isNotEmpty() ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ $missionsHorsZone->count() }} mission(s) détectée(s) aujourd’hui en dehors de vos zones assignées.
                                </p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $missionsHorsZone->isNotEmpty() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $missionsHorsZone->count() }}</span>
                        </div>

                        @if($missionsHorsZone->isNotEmpty())
                        <div class="mt-4 space-y-3">
                            @foreach($missionsHorsZone as $rdv)
                            <div class="rounded-xl border border-red-200 bg-white p-3">
                                <p class="font-medium text-slate-900">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                                <p class="text-sm text-slate-600">{{ $rdv->client->name ?? 'Client' }} • {{ substr((string) $rdv->heure, 0, 5) }}</p>
                                <p class="text-sm text-red-700">{{ $rdv->serviceZone?->name ?? 'Zone non définie' }}</p>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="p-5" title="Mes limites de RDV par jour" subtitle="Ajustez rapidement votre capacité hebdomadaire." eyebrow="Capacité">
                <div class="space-y-2">
                    @foreach(\Carbon\Carbon::now()->startOfWeek()->daysUntil(\Carbon\Carbon::now()->endOfWeek()) as $jour)
                    <div class="cu-list-item flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="text-sm font-medium text-slate-700 md:w-1/3">
                            {{ $jour->translatedFormat('l d F') }}
                        </div>
                        <div class="md:w-2/3">
                            @livewire('modifier-limite-jour', [
                            'date' => $jour->format('Y-m-d'),
                            'user_id' => auth()->id(),
                            'fromAdmin' => false
                            ], key($jour->format('Ymd') . '-' . auth()->id()))
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card padding="p-5" title="Accès rapides employé" subtitle="Les raccourcis les plus utiles pour piloter votre journée." eyebrow="Raccourcis">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-ui.action-button :href="route('employe.feedbacks')" icon="💬">Voir tous mes feedbacks</x-ui.action-button>
                    <x-ui.action-button :href="route('employe.validation.multiple')" variant="amber" icon="✅">Validation groupée des RDV</x-ui.action-button>
                </div>
            </x-ui.card>

            <livewire:feedbacks-employe />
            <livewire:employe.feedback-stats />
            <livewire:employe.validation-multiple-rdv />
        </div>
    </div>
</div>


<script>
    const OFFLINE_QUEUE_KEY = 'cleanux_offline_actions';

    function getOfflineQueue() {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    }

    function saveOfflineQueue(queue) {
        localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    }

    function queueOfflineAction(type, missionId, payload = {}) {
        const queue = getOfflineQueue();

        queue.push({
            type: type,
            mission_id: missionId,
            payload: payload,
            created_at: new Date().toISOString(),
        });

        saveOfflineQueue(queue);
    }

    async function syncOfflineActions() {
        const queue = getOfflineQueue();

        if (!queue.length || !navigator.onLine) {
            return;
        }

        const response = await fetch('/missions/offline-sync', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                actions: queue,
            }),
        });

        const result = await response.json();

        if (result.ok) {
            saveOfflineQueue([]);
            console.log(`${result.synced} action(s) synchronisée(s).`);
        }
    }

    window.addEventListener('online', syncOfflineActions);

    setInterval(syncOfflineActions, 30000);
</script>