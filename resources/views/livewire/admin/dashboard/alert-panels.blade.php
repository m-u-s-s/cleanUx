<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-red-600">Alertes critiques</p>
                <h3 class="text-xl font-black text-slate-900">Urgences trop anciennes</h3>
                <p class="text-sm text-slate-500">Demandes urgentes encore bloquées en attente.</p>
            </div>

            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700 ring-1 ring-red-200">
                {{ $urgencesVieillissantes->count() ?? 0 }} alerte(s)
            </span>
        </div>

        <div class="space-y-4">
            @forelse($urgencesVieillissantes as $rdv)
                <div class="rounded-2xl border border-red-100 bg-red-50/70 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">
                                🚨 {{ $rdv->client->name ?? 'Client' }}
                            </p>

                            <p class="mt-1 text-sm text-slate-600">
                                {{ $rdv->service_display_name ?: 'Service non précisé' }}
                            </p>

                            <p class="mt-2 text-sm text-slate-500">
                                📅 {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                · 🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>

                            <p class="mt-1 text-xs font-semibold text-red-700">
                                En attente depuis plus de 4h
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

                        <button wire:click="ouvrirPlanning({{ $rdv->id }})"
                            class="rounded-xl bg-white px-3 py-2 text-xs font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                            🗓️ Replanifier
                        </button>
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucune urgence vieillissante" message="Aucune demande urgente n’est bloquée pour le moment." icon="✅" />
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl border border-orange-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-orange-600">Optimisation</p>
                <h3 class="text-xl font-black text-slate-900">Services sous-estimés</h3>
                <p class="text-sm text-slate-500">Services qui dépassent souvent la durée prévue.</p>
            </div>

            <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700 ring-1 ring-orange-200">
                {{ $servicesSousEstimes->count() ?? 0 }} service(s)
            </span>
        </div>

        <div class="space-y-4">
            @forelse($servicesSousEstimes as $service => $row)
                <div class="rounded-2xl border border-orange-100 bg-orange-50/70 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-black text-slate-900">
                                ⏱️ {{ ucfirst(str_replace('_', ' ', $service)) }}
                            </p>

                            <p class="mt-1 text-sm text-slate-600">
                                Ce service dépasse régulièrement l’estimation.
                            </p>

                            <p class="mt-2 text-xs font-semibold text-orange-700">
                                Base : {{ $row['count'] }} mission(s)
                            </p>
                        </div>

                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-orange-700 ring-1 ring-orange-200">
                            +{{ $row['avg_gap'] }} min
                        </span>
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucun service critique" message="Les durées semblent cohérentes pour le moment." icon="👌" />
            @endforelse
        </div>
    </div>
</div>