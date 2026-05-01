@php
    $formatDate = function ($date) {
        if (! $date) {
            return 'Date inconnue';
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    };

    $formatTime = function ($time) {
        return $time ? substr((string) $time, 0, 5) : 'Heure inconnue';
    };

    $serviceLabel = function ($rdv) {
        return $rdv->service_display_name ?: 'Service non précisé';
    };

    $cityLabel = function ($rdv) {
        return $rdv->ville ?: ($rdv->postal_code ?: 'Localisation inconnue');
    };
@endphp

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    {{-- Interventions du jour --}}
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">
                    Aujourd’hui
                </p>
                <h3 class="mt-1 text-xl font-black text-slate-900">
                    Interventions du jour
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Planning opérationnel immédiat.
                </p>
            </div>

            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-100">
                {{ $interventionsDuJour->count() ?? 0 }} RDV
            </span>
        </div>

        <div class="space-y-4">
            @forelse($interventionsDuJour as $rdv)
                <article class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4 transition hover:border-blue-200 hover:bg-blue-50/40">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-black text-slate-900">
                                {{ $rdv->client->name ?? 'Client inconnu' }}
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-700">
                                {{ $serviceLabel($rdv) }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                <span class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                    📅 {{ $formatDate($rdv->date) }}
                                </span>

                                <span class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                    🕒 {{ $formatTime($rdv->heure) }}
                                </span>

                                <span class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                    📍 {{ $cityLabel($rdv) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                        <button wire:click="ouvrirMission({{ $rdv->id }})"
                                class="rounded-2xl bg-blue-600 px-3 py-2 text-xs font-black text-white shadow-sm transition hover:bg-blue-700">
                            👁️ Voir détail
                        </button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})"
                                    class="rounded-2xl bg-amber-50 px-3 py-2 text-xs font-black text-amber-700 ring-1 ring-amber-200 transition hover:bg-amber-100">
                                🗓️ Replanifier
                            </button>
                        @endif
                    </div>
                </article>
            @empty
                <x-empty-state
                    title="Aucune intervention aujourd’hui"
                    message="Le planning du jour est vide."
                    icon="📆" />
            @endforelse
        </div>
    </section>

    {{-- Charge employés --}}
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">
                    Charge terrain
                </p>
                <h3 class="mt-1 text-xl font-black text-slate-900">
                    Charge des employés
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Vue rapide des surcharges du jour.
                </p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($chargeEmployes as $item)
                @php
                    $minutes = $item['minutes'] ?? 0;
                    $tone = $minutes >= 480 ? 'red' : ($minutes >= 300 ? 'amber' : 'emerald');
                    $label = $minutes >= 480 ? 'Surchargé' : ($minutes >= 300 ? 'Chargé' : 'OK');
                    $barWidth = min(100, round(($minutes / 480) * 100));
                @endphp

                <article class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-black text-slate-900">
                                {{ $item['employe']->name ?? 'Employé inconnu' }}
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ $item['count'] ?? 0 }} intervention(s)
                                · {{ $minutes }} min
                                · {{ $item['hours'] ?? 0 }} h
                            </p>
                        </div>

                        <span class="rounded-full px-3 py-1 text-xs font-black
                            {{ $tone === 'red' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : '' }}
                            {{ $tone === 'amber' ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : '' }}
                            {{ $tone === 'emerald' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : '' }}">
                            {{ $label }}
                        </span>
                    </div>

                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full
                            {{ $tone === 'red' ? 'bg-red-500' : '' }}
                            {{ $tone === 'amber' ? 'bg-amber-500' : '' }}
                            {{ $tone === 'emerald' ? 'bg-emerald-500' : '' }}"
                             style="width: {{ $barWidth }}%">
                        </div>
                    </div>
                </article>
            @empty
                <x-empty-state
                    title="Aucun employé trouvé"
                    message="Les charges employés apparaîtront ici."
                    icon="👥" />
            @endforelse
        </div>
    </section>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    {{-- Urgences --}}
    <section class="rounded-[2rem] border border-red-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-red-600">
                    Priorité
                </p>
                <h3 class="mt-1 text-xl font-black text-slate-900">
                    Interventions urgentes
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Demandes à traiter rapidement.
                </p>
            </div>

            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700 ring-1 ring-red-100">
                {{ $urgences->count() ?? 0 }} urgence(s)
            </span>
        </div>

        <div class="space-y-4">
            @forelse($urgences as $rdv)
                <article class="rounded-3xl border border-red-100 bg-red-50/70 p-4 transition hover:border-red-200 hover:bg-red-50">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-black text-slate-900">
                                {{ $rdv->client->name ?? 'Client inconnu' }}
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-700">
                                {{ $serviceLabel($rdv) }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                <span class="rounded-full bg-white px-3 py-1 ring-1 ring-red-100">
                                    📅 {{ $formatDate($rdv->date) }}
                                </span>

                                <span class="rounded-full bg-white px-3 py-1 ring-1 ring-red-100">
                                    🕒 {{ $formatTime($rdv->heure) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-red-100 pt-4">
                        <button wire:click="ouvrirMission({{ $rdv->id }})"
                                class="rounded-2xl bg-red-600 px-3 py-2 text-xs font-black text-white shadow-sm transition hover:bg-red-700">
                            👁️ Voir détail
                        </button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})"
                                    class="rounded-2xl bg-white px-3 py-2 text-xs font-black text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                                🗓️ Replanifier
                            </button>
                        @endif
                    </div>
                </article>
            @empty
                <x-empty-state
                    title="Aucune urgence"
                    message="Aucune intervention urgente pour le moment."
                    icon="🚨" />
            @endforelse
        </div>
    </section>

    {{-- Missions terminées --}}
    <section class="rounded-[2rem] border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">
                    Qualité
                </p>
                <h3 class="mt-1 text-xl font-black text-slate-900">
                    Missions terminées
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Dernières interventions clôturées.
                </p>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($missionsTerminees as $rdv)
                <article class="rounded-3xl border border-emerald-100 bg-emerald-50/70 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-black text-slate-900">
                                {{ $rdv->client->name ?? 'Client inconnu' }}
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-700">
                                {{ $serviceLabel($rdv) }}
                            </p>

                            <p class="mt-3 text-xs font-semibold text-emerald-700">
                                ✅ Terminée · {{ $formatDate($rdv->date) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>

                    <div class="mt-4 border-t border-emerald-100 pt-4">
                        <button wire:click="ouvrirMission({{ $rdv->id }})"
                                class="rounded-2xl bg-emerald-600 px-3 py-2 text-xs font-black text-white shadow-sm transition hover:bg-emerald-700">
                            👁️ Voir détail
                        </button>
                    </div>
                </article>
            @empty
                <x-empty-state
                    title="Aucune mission terminée"
                    message="Les missions clôturées apparaîtront ici."
                    icon="✅" />
            @endforelse
        </div>
    </section>
</div>