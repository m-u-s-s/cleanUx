<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="space-y-6">
        <div class="cu-card p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Rendez-vous sans employé attribué</h3>
                    <p class="text-sm text-slate-500">Demandes standard à traiter rapidement</p>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($rendezVousSansEmploye as $rdv)
                    <div class="cu-list-item flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">
                                {{ $rdv->client->name ?? 'Client' }} — {{ $rdv->service_display_name ?: 'Service non précisé' }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $rdv->date }} à {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>
                            <p class="text-sm text-slate-500">
                                {{ $rdv->adresse ?? 'Adresse non précisée' }}, {{ $rdv->ville ?? '—' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>
                @empty
                    <x-empty-state title="Aucun rendez-vous sans employé" message="Les demandes non assignées apparaîtront ici pour accélérer la prise en charge opérationnelle." icon="🧭" />
                @endforelse
            </div>
        </div>

        <div class="cu-card p-6 border-amber-200">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Rendez-vous Premium</h3>
                    <p class="text-sm text-slate-500">Demandes clients premium et suivi personnalisé</p>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($premiumRendezVous as $rdv)
                    <div class="cu-list-item flex flex-col gap-4 border-amber-100 bg-amber-50 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">★ {{ $rdv->client->name ?? 'Client Premium' }}</p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $rdv->service_display_name ?: 'Service non précisé' }} — {{ $rdv->date }} à {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>
                            <p class="text-sm text-slate-500">Employé : {{ $rdv->employe->name ?? 'À confirmer' }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-badge :status="$rdv->status" />
                            <x-priority-badge :priority="$rdv->priorite" />
                        </div>
                    </div>
                @empty
                    <x-empty-state title="Aucun rendez-vous premium" message="Les prochaines demandes premium apparaîtront ici avec un suivi dédié." icon="★" />
                @endforelse
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="cu-card p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Clients Premium actifs</h3>
                    <p class="text-sm text-slate-500">Suivi des clients à forte valeur</p>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($premiumClients as $client)
                    <div class="cu-list-item flex items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $client->name }}</p>
                            <p class="text-sm text-slate-500">{{ $client->email }}</p>
                        </div>

                        <div class="text-right">
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                Premium actif
                            </span>
                        </div>
                    </div>
                @empty
                    <x-empty-state title="Aucun client premium actif" message="Les comptes premium actifs apparaîtront ici pour un suivi plus rapide." icon="👑" />
                @endforelse
            </div>
        </div>

        <div class="cu-card p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Premium sans employés favoris</h3>
                    <p class="text-sm text-slate-500">Clients premium à accompagner pour mieux personnaliser leur expérience</p>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($premiumClientsWithoutFavorites as $client)
                    <div class="cu-list-item">
                        <p class="font-semibold text-slate-900">{{ $client->name }}</p>
                        <p class="text-sm text-slate-500">{{ $client->email }}</p>
                    </div>
                @empty
                    <x-empty-state title="Tous les clients premium ont des favoris" message="Aucun accompagnement particulier à prévoir sur ce point pour le moment." icon="👌" />
                @endforelse
            </div>
        </div>
    </div>
</div>
