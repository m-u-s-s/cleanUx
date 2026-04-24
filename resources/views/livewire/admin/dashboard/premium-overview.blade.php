<div class="space-y-6">
    <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Premium</p>
                <h3 class="text-xl font-black text-slate-900">Clients Premium actifs</h3>
                <p class="text-sm text-slate-500">Clients à forte valeur et suivi personnalisé.</p>
            </div>

            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">
                {{ $premiumClients->count() ?? 0 }} client(s)
            </span>
        </div>

        <div class="space-y-3">
            @forelse($premiumClients as $client)
                <div class="rounded-2xl border border-amber-100 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-black text-slate-900">★ {{ $client->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $client->email }}</p>
                        </div>

                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
                            Actif
                        </span>
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucun premium actif" message="Les clients premium actifs apparaîtront ici." icon="👑" />
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">À traiter</p>
                <h3 class="text-xl font-black text-slate-900">RDV sans employé</h3>
                <p class="text-sm text-slate-500">Demandes à attribuer rapidement.</p>
            </div>

            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">
                {{ $rendezVousSansEmploye->count() ?? 0 }} attente(s)
            </span>
        </div>

        <div class="space-y-3">
            @forelse($rendezVousSansEmploye as $rdv)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">{{ $rdv->client->name ?? 'Client' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                            <p class="mt-2 text-sm text-slate-500">
                                📅 {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                · 🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                📍 {{ $rdv->adresse ?? 'Adresse non précisée' }}, {{ $rdv->ville ?? '—' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>
                </div>
            @empty
                <x-empty-state title="Tout est assigné" message="Aucun rendez-vous sans employé pour le moment." icon="🧭" />
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl border border-amber-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Suivi premium</p>
                <h3 class="text-xl font-black text-slate-900">RDV Premium</h3>
                <p class="text-sm text-slate-500">Demandes premium avec priorité de suivi.</p>
            </div>

            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200">
                {{ $premiumRendezVous->count() ?? 0 }} RDV
            </span>
        </div>

        <div class="space-y-3">
            @forelse($premiumRendezVous as $rdv)
                <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">★ {{ $rdv->client->name ?? 'Client Premium' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                            <p class="mt-2 text-sm text-slate-500">
                                📅 {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                · 🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                Employé : {{ $rdv->employe->name ?? 'À confirmer' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucun RDV premium" message="Les demandes premium apparaîtront ici." icon="★" />
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Personnalisation</p>
            <h3 class="text-xl font-black text-slate-900">Premium sans favoris</h3>
            <p class="text-sm text-slate-500">Clients premium à accompagner pour choisir leurs employés favoris.</p>
        </div>

        <div class="space-y-3">
            @forelse($premiumClientsWithoutFavorites as $client)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="font-black text-slate-900">{{ $client->name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $client->email }}</p>
                </div>
            @empty
                <x-empty-state title="Tout est configuré" message="Tous les clients premium ont des favoris." icon="👌" />
            @endforelse
        </div>
    </div>
</div>