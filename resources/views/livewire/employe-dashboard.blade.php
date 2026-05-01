@php
    $statsJour = $statsJour ?? [
        'total' => 0,
        'a_faire' => 0,
        'en_cours' => 0,
        'terminees' => 0,
        'refusees' => 0,
        'urgentes' => 0,
        'heures_prevues' => 0,
        'progression' => 0,
    ];

    $missionsDuJour = $missionsDuJour ?? collect();
    $historiqueRecent = $historiqueRecent ?? collect();
    $assignedZones = $assignedZones ?? collect();
    $missionsHorsZone = $missionsHorsZone ?? collect();
    $urgencesDuJour = $urgencesDuJour ?? collect();
    $prochaineMission = $prochaineMission ?? null;
    $paymentStatus = $paymentStatus ?? ['ready' => false, 'label' => 'Paiement à configurer'];
@endphp

<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-10 pt-6 sm:px-6 lg:px-8">
        <x-active-sessions />

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.85fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-300">
                        Portail employé
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Ma journée terrain
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Vue rapide de vos missions, priorités, zones, actions terrain et historique récent.
                        L’objectif est simple : savoir quoi faire maintenant, où aller et quoi clôturer.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        @if(Route::has('employe.missions'))
                            <a href="{{ route('employe.missions') }}" class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                                📋 Toutes mes missions
                            </a>
                        @endif

                        @if(Route::has('employe.planning'))
                            <a href="{{ route('employe.planning') }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                📅 Mon planning
                            </a>
                        @endif

                        @if(Route::has('employe.historique'))
                            <a href="{{ route('employe.historique') }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                🕘 Historique
                            </a>
                        @endif

                        @if(Route::has('employe.incident'))
                            <a href="{{ route('employe.incident') }}" class="rounded-2xl border border-rose-300/30 bg-rose-400/10 px-4 py-2 text-sm font-bold text-rose-100 transition hover:bg-rose-400/20">
                                ⚠️ Signaler incident
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Aujourd’hui
                        </p>
                        <p class="mt-2 text-3xl font-black text-white">
                            {{ $statsJour['total'] }} mission(s)
                        </p>
                        <p class="mt-1 text-sm text-slate-200">
                            {{ $statsJour['heures_prevues'] }} h prévues · {{ $statsJour['progression'] }}% terminé
                        </p>

                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/20">
                            <div class="h-full rounded-full bg-emerald-400" style="width: {{ min(100, max(0, $statsJour['progression'])) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-3xl border {{ $paymentStatus['ready'] ? 'border-emerald-300/20 bg-emerald-400/10' : 'border-amber-300/20 bg-amber-400/10' }} p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $paymentStatus['ready'] ? 'text-emerald-200' : 'text-amber-200' }}">
                            Paiement prestataire
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $paymentStatus['label'] }}
                        </p>

                        @if(! $paymentStatus['ready'] && Route::has('employe.stripe-connect.start'))
                            <a href="{{ route('employe.stripe-connect.start') }}" class="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 hover:bg-slate-100">
                                Configurer mes paiements
                            </a>
                        @else
                            <p class="mt-2 text-sm text-emerald-50">
                                Votre compte est prêt pour recevoir les reversements.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 xl:grid-cols-6">
            <x-ui.stat title="Total" :value="$statsJour['total']" tone="slate" icon="📦" hint="Missions du jour" />
            <x-ui.stat title="À faire" :value="$statsJour['a_faire']" tone="amber" icon="⏳" hint="Encore à démarrer" />
            <x-ui.stat title="En cours" :value="$statsJour['en_cours']" tone="blue" icon="🚚" hint="En intervention" />
            <x-ui.stat title="Terminées" :value="$statsJour['terminees']" tone="green" icon="✅" hint="Clôturées" />
            <x-ui.stat title="Urgentes" :value="$statsJour['urgentes']" tone="red" icon="🚨" hint="À prioriser" />
            <x-ui.stat title="Progression" :value="$statsJour['progression'] . '%'" tone="emerald" icon="📈" hint="Avancement du jour" />
        </section>

        @if($prochaineMission)
            <section class="overflow-hidden rounded-[2rem] bg-gradient-to-r from-blue-700 via-sky-700 to-indigo-700 p-6 text-white shadow-[0_18px_50px_rgba(37,99,235,0.22)]">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">
                            Prochaine mission
                        </p>

                        <h2 class="mt-1 text-2xl font-black">
                            {{ $prochaineMission->service_display_name ?: 'Service non précisé' }}
                        </h2>

                        <p class="mt-2 text-sm text-blue-100">
                            📅 {{ $prochaineMission->date }} à {{ substr((string) $prochaineMission->heure, 0, 5) }}
                        </p>

                        <p class="mt-1 text-sm text-blue-100">
                            👤 {{ $prochaineMission->client->name ?? 'Client' }}
                            · 📍 {{ $prochaineMission->adresse ?? 'Adresse non précisée' }}, {{ $prochaineMission->ville ?? '—' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($prochaineMission->telephone_client)
                            <a href="tel:{{ $prochaineMission->telephone_client }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                                📞 Appeler
                            </a>
                        @endif

                        @if($prochaineMission->adresse || $prochaineMission->ville)
                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($prochaineMission->adresse ?? '') . ' ' . ($prochaineMission->ville ?? '')) }}" target="_blank" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                                📍 GPS
                            </a>
                        @endif

                        @if(Route::has('employe.missions'))
                            <a href="{{ route('employe.missions') }}" class="inline-flex items-center rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/20">
                                Voir workspace
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <div class="space-y-6">
                <x-ui.card padding="p-5" title="Missions du jour" subtitle="Triées par ordre d’exécution et statut terrain." eyebrow="Aujourd’hui">
                    <div class="space-y-4">
                        @forelse($missionsDuJour as $rdv)
                            <div class="cu-list-item {{ $rdv->status === 'sur_place' ? 'ring-2 ring-indigo-200 border-indigo-300' : '' }}">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </h4>

                                        <p class="mt-1 text-sm text-slate-600">
                                            👤 {{ $rdv->client->name ?? 'Client' }}
                                        </p>

                                        <p class="text-sm text-slate-600">
                                            🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                                            · 📍 {{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}
                                        </p>
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
                                        <a href="tel:{{ $rdv->telephone_client }}" class="inline-flex items-center rounded-xl bg-green-100 px-3 py-2 text-sm font-medium text-green-700 transition hover:bg-green-200">
                                            📞 Appeler
                                        </a>
                                    @endif

                                    @if($rdv->adresse || $rdv->ville)
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? '')) }}" target="_blank" class="inline-flex items-center rounded-xl bg-blue-100 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-200">
                                            📍 GPS
                                        </a>
                                    @endif

                                    @if($rdv->mission?->report_path)
                                        <a href="{{ asset('storage/'.$rdv->mission->report_path) }}" target="_blank" class="inline-flex items-center rounded-xl bg-emerald-100 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-200">
                                            📄 Rapport
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <x-ui.empty-state title="Aucune mission aujourd’hui" message="Les nouvelles missions assignées apparaîtront ici automatiquement." icon="🗓️" />
                        @endforelse
                    </div>
                </x-ui.card>

                <x-ui.card padding="p-5" title="Gestion complète des missions" subtitle="Suivi opérationnel, changement de statut et actions terrain." eyebrow="Workspace terrain">
                    <livewire:employe.mes-rendez-vous />
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card padding="p-5" title="Actions prioritaires" subtitle="Ce qui mérite votre attention maintenant." eyebrow="Priorités">
                    <div class="space-y-3">
                        @forelse($urgencesDuJour as $rdv)
                            <div class="rounded-2xl border {{ $rdv->priorite === 'urgente' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ substr((string) $rdv->heure, 0, 5) }} · {{ $rdv->client->name ?? 'Client' }}
                                        </p>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty-state title="Aucune urgence" message="Aucune action critique détectée pour votre journée." icon="✅" />
                        @endforelse
                    </div>
                </x-ui.card>

                <x-ui.card padding="p-5" title="Zones assignées" subtitle="Vos zones de couverture actives et les éventuels écarts." eyebrow="Couverture">
                    <div class="space-y-4">
                        <div>
                            <p class="mb-2 text-sm font-medium text-slate-700">
                                Zones actives
                            </p>

                            <div class="flex flex-wrap gap-2">
                                @forelse($assignedZones as $zone)
                                    <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                        {{ $zone->name }}
                                    </span>
                                @empty
                                    <span class="text-sm text-slate-500">Aucune zone assignée.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-2xl border {{ $missionsHorsZone->isNotEmpty() ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-semibold {{ $missionsHorsZone->isNotEmpty() ? 'text-red-700' : 'text-emerald-700' }}">
                                        Mission(s) hors zone
                                    </h4>

                                    <p class="mt-1 text-sm {{ $missionsHorsZone->isNotEmpty() ? 'text-red-600' : 'text-emerald-600' }}">
                                        {{ $missionsHorsZone->count() }} mission(s) détectée(s) aujourd’hui en dehors de vos zones assignées.
                                    </p>
                                </div>

                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $missionsHorsZone->isNotEmpty() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $missionsHorsZone->count() }}
                                </span>
                            </div>

                            @if($missionsHorsZone->isNotEmpty())
                                <div class="mt-4 space-y-3">
                                    @foreach($missionsHorsZone as $rdv)
                                        <div class="rounded-xl border border-red-200 bg-white p-3">
                                            <p class="font-medium text-slate-900">
                                                {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                            </p>
                                            <p class="text-sm text-slate-600">
                                                {{ $rdv->client->name ?? 'Client' }} · {{ substr((string) $rdv->heure, 0, 5) }}
                                            </p>
                                            <p class="text-sm text-red-700">
                                                {{ $rdv->serviceZone?->name ?? 'Zone non définie' }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card padding="p-5" title="Historique récent" subtitle="Vos dernières missions terminées." eyebrow="Suivi">
                    <div class="space-y-3">
                        @forelse($historiqueRecent as $rdv)
                            <div class="cu-list-item">
                                <p class="font-medium text-slate-900">
                                    {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                </p>
                                <p class="text-sm text-slate-600">
                                    {{ $rdv->date }} à {{ substr((string) $rdv->heure, 0, 5) }}
                                </p>
                                <p class="text-sm text-slate-600">
                                    {{ $rdv->client->name ?? 'Client' }}
                                </p>

                                @if($rdv->duree_reelle)
                                    <p class="mt-1 text-xs text-slate-500">
                                        Durée réelle : {{ $rdv->duree_reelle }} min
                                    </p>
                                @endif
                            </div>
                        @empty
                            <x-ui.empty-state title="Aucun historique récent" message="Votre historique de missions terminées apparaîtra ici." icon="🧾" />
                        @endforelse
                    </div>
                </x-ui.card>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <x-ui.card padding="p-5" title="Capacité hebdomadaire" subtitle="Ajustez rapidement votre limite de rendez-vous par jour." eyebrow="Disponibilités">
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
                    @if(Route::has('employe.feedbacks'))
                        <x-ui.action-button :href="route('employe.feedbacks')" icon="💬">
                            Voir mes feedbacks
                        </x-ui.action-button>
                    @endif

                    @if(Route::has('employe.validation.multiple'))
                        <x-ui.action-button :href="route('employe.validation.multiple')" variant="amber" icon="✅">
                            Validation groupée
                        </x-ui.action-button>
                    @endif

                    @if(Route::has('employe.disponibilites'))
                        <x-ui.action-button :href="route('employe.disponibilites')" icon="🕒">
                            Disponibilités
                        </x-ui.action-button>
                    @endif

                    @if(Route::has('employe.coordination'))
                        <x-ui.action-button :href="route('employe.coordination')" icon="🧭">
                            Coordination
                        </x-ui.action-button>
                    @endif
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4">
                    <livewire:feedbacks-employe />
                    <livewire:employe.feedback-stats />
                    <livewire:employe.validation-multiple-rdv />
                </div>
            </x-ui.card>
        </section>
    </div>
</div>

@push('scripts')
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

        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!token) {
            return;
        }

        try {
            const response = await fetch('/missions/offline-sync', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
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
        } catch (error) {
            console.warn('Synchronisation offline impossible pour le moment.', error);
        }
    }

    window.addEventListener('online', syncOfflineActions);
    setInterval(syncOfflineActions, 30000);
</script>
@endpush
