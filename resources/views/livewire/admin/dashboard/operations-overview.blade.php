<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Aujourd’hui</p>
                <h3 class="text-xl font-black text-slate-900">Interventions du jour</h3>
                <p class="text-sm text-slate-500">Planning opérationnel immédiat.</p>
            </div>

            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">
                {{ $interventionsDuJour->count() ?? 0 }} RDV
            </span>
        </div>

        <div class="space-y-4">
            @forelse($interventionsDuJour as $rdv)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">
                                {{ $rdv->client->name ?? 'Client' }}
                            </p>

                            <p class="mt-1 text-sm text-slate-600">
                                {{ $rdv->service_display_name ?: 'Service non précisé' }}
                            </p>

                            <p class="mt-2 text-sm text-slate-500">
                                📅 {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                · 🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                📍 {{ $rdv->ville ?? 'Ville inconnue' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                        <button wire:click="ouvrirMission({{ $rdv->id }})"
                            class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                            👁️ Voir détail
                        </button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})"
                                class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100">
                                🗓️ Replanifier
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucune intervention aujourd’hui" message="Le planning du jour est vide." icon="📆" />
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Charge terrain</p>
                <h3 class="text-xl font-black text-slate-900">Charge des employés</h3>
                <p class="text-sm text-slate-500">Vue rapide des surcharges du jour.</p>
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

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-black text-slate-900">{{ $item['employe']->name }}</p>
                            <p class="text-sm text-slate-500">
                                {{ $item['count'] }} intervention(s) · {{ $minutes }} min · {{ $item['hours'] }} h
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
                </div>
            @empty
                <x-empty-state title="Aucun employé trouvé" message="Les charges employés apparaîtront ici." icon="👥" />
            @endforelse
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-red-600">Priorité</p>
                <h3 class="text-xl font-black text-slate-900">Interventions urgentes</h3>
                <p class="text-sm text-slate-500">Demandes à traiter rapidement.</p>
            </div>

            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                {{ $urgences->count() ?? 0 }} urgence(s)
            </span>
        </div>

        <div class="space-y-4">
            @forelse($urgences as $rdv)
                <div class="rounded-2xl border border-red-100 bg-red-50/60 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">{{ $rdv->client->name ?? 'Client' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                            <p class="mt-2 text-sm text-slate-500">
                                📅 {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }} · 🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-red-100 pt-4">
                        <button wire:click="ouvrirMission({{ $rdv->id }})"
                            class="rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">
                            👁️ Voir détail
                        </button>

                        @if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place']))
                            <button wire:click="ouvrirPlanning({{ $rdv->id }})"
                                class="rounded-xl bg-white px-3 py-2 text-xs font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                🗓️ Replanifier
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucune urgence" message="Aucune intervention urgente pour le moment." icon="🚨" />
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">Qualité</p>
                <h3 class="text-xl font-black text-slate-900">Missions terminées</h3>
                <p class="text-sm text-slate-500">Dernières interventions clôturées.</p>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($missionsTerminees as $rdv)
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">{{ $rdv->client->name ?? 'Client' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                            <p class="mt-2 text-sm text-slate-500">
                                ✅ Terminée · {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>

                    <div class="mt-4 border-t border-emerald-100 pt-4">
                        <button wire:click="ouvrirMission({{ $rdv->id }})"
                            class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">
                            👁️ Voir détail
                        </button>
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucune mission terminée" message="Les missions clôturées apparaîtront ici." icon="✅" />
            @endforelse
        </div>
    </div>
</div>