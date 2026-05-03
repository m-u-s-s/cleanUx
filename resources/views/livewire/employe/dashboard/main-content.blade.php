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
