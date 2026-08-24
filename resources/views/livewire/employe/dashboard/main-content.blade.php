<section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.3fr_0.7fr]" aria-live="polite">
    <div class="space-y-6">
        <x-ui.card padding="p-5" title="Missions du jour" subtitle="Triées par ordre d'exécution et statut terrain." eyebrow="Aujourd'hui">
            <div class="space-y-3">
                @forelse($missionsDuJour as $rdv)
                    <div class="rounded-xl border {{ $rdv->status === 'sur_place' ? 'border-brand-300 bg-brand-50/30 ring-2 ring-brand-100 dark:border-brand-700 dark:bg-brand-900/20' : 'border-slate-200/70 bg-white dark:border-slate-700 dark:bg-slate-800/60' }} p-4 transition hover:shadow-soft-sm">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900 truncate dark:text-slate-100">
                                    {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                </h4>

                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="user" class="w-3.5 h-3.5 text-slate-400" />
                                        {{ $rdv->client->name ?? 'Client' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="clock" class="w-3.5 h-3.5 text-slate-400" />
                                        {{ substr((string) $rdv->heure, 0, 5) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 min-w-0">
                                        <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span class="truncate">{{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <x-badge :status="$rdv->status" />
                                <x-priority-badge :priority="$rdv->priorite" />
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-700 md:grid-cols-2 dark:text-slate-300">
                            <div class="flex gap-2">
                                <dt class="text-slate-500 dark:text-slate-400">Téléphone :</dt>
                                <dd class="font-medium">{{ $rdv->telephone_client ?? '—' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-slate-500 dark:text-slate-400">Durée estimée :</dt>
                                <dd class="font-medium">{{ $rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-slate-500 dark:text-slate-400">Type de lieu :</dt>
                                <dd class="font-medium">{{ ucfirst($rdv->place_type ?? '—') }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-slate-500 dark:text-slate-400">Surface :</dt>
                                <dd class="font-medium">{{ $rdv->surface ?? '—' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-slate-500 dark:text-slate-400">Parking :</dt>
                                <dd class="font-medium">{{ $rdv->acces_parking ? 'Oui' : 'Non' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-slate-500 dark:text-slate-400">Animaux :</dt>
                                <dd class="font-medium">{{ $rdv->presence_animaux ? 'Oui' : 'Non' }}</dd>
                            </div>
                        </dl>

                        @if($rdv->commentaire_client)
                            <div class="mt-3 rounded-lg border border-amber-200/70 bg-amber-50/50 p-2.5 text-xs text-amber-900">
                                <span class="font-semibold">Remarque client :</span>
                                {{ $rdv->commentaire_client }}
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if($rdv->telephone_client)
                                <a href="tel:{{ $rdv->telephone_client }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                    <x-ui.icon name="phone" class="w-3.5 h-3.5" />
                                    Appeler
                                </a>
                            @endif

                            @if($rdv->adresse || $rdv->ville)
                                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? '')) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition">
                                    <x-ui.icon name="map-pin" class="w-3.5 h-3.5" />
                                    GPS
                                </a>
                            @endif

                            @if($rdv->mission?->report_path)
                                <a href="{{ \App\Support\Media\PrivateMedia::url($rdv->mission->report_path) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition">
                                    <x-ui.icon name="document" class="w-3.5 h-3.5" />
                                    Rapport
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state title="Aucune mission aujourd'hui" message="Les nouvelles missions assignées apparaîtront ici automatiquement." icon="🗓️" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card padding="p-5" title="Gestion complète des missions" subtitle="Suivi opérationnel, statuts et actions terrain." eyebrow="Workspace terrain">
            <livewire:employe.mes-rendez-vous />
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card padding="p-5" title="Actions prioritaires" subtitle="Ce qui mérite votre attention maintenant." eyebrow="Priorités">
            <div class="space-y-2.5">
                @forelse($urgencesDuJour as $rdv)
                    <div class="rounded-xl border {{ $rdv->priorite === 'urgente' ? 'border-rose-200 bg-rose-50/50' : 'border-amber-200 bg-amber-50/50' }} p-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">
                                    {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-600">
                                    {{ substr((string) $rdv->heure, 0, 5) }} · {{ $rdv->client->name ?? 'Client' }}
                                </p>
                            </div>

                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                <x-badge :status="$rdv->status" />
                                <x-priority-badge :priority="$rdv->priorite" />
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state title="Aucune urgence" message="Aucune action critique pour votre journée." icon="✅" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card padding="p-5" title="Zones assignées" subtitle="Couverture active et écarts détectés." eyebrow="Couverture">
            <div class="space-y-4">
                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                        Zones actives
                    </p>

                    <div class="flex flex-wrap gap-1.5">
                        @forelse($assignedZones as $zone)
                            <span class="inline-flex items-center gap-1 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                                <x-ui.icon name="map-pin" class="w-3 h-3" />
                                {{ $zone->name }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-500">Aucune zone assignée.</span>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border {{ $missionsHorsZone->isNotEmpty() ? 'border-rose-200 bg-rose-50/50' : 'border-emerald-200 bg-emerald-50/50' }} p-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="inline-flex items-center gap-1.5 text-sm font-semibold {{ $missionsHorsZone->isNotEmpty() ? 'text-rose-700' : 'text-emerald-700' }}">
                                <x-ui.icon name="{{ $missionsHorsZone->isNotEmpty() ? 'exclamation-triangle' : 'check-circle' }}" class="w-3.5 h-3.5" />
                                Mission(s) hors zone
                            </h4>

                            <p class="mt-1 text-xs {{ $missionsHorsZone->isNotEmpty() ? 'text-rose-700' : 'text-emerald-700' }}">
                                {{ $missionsHorsZone->count() }} mission(s) détectée(s) aujourd'hui hors zones assignées.
                            </p>
                        </div>

                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold {{ $missionsHorsZone->isNotEmpty() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                            {{ $missionsHorsZone->count() }}
                        </span>
                    </div>

                    @if($missionsHorsZone->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach($missionsHorsZone as $rdv)
                                <div class="rounded-lg border border-rose-200 bg-white p-2.5">
                                    <p class="text-xs font-semibold text-slate-900 truncate">
                                        {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                    </p>
                                    <p class="text-xs text-slate-600">
                                        {{ $rdv->client->name ?? 'Client' }} · {{ substr((string) $rdv->heure, 0, 5) }}
                                    </p>
                                    <p class="text-xs text-rose-700">
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
            <div class="space-y-2">
                @forelse($historiqueRecent as $rdv)
                    <div class="rounded-lg border border-slate-200/70 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="text-sm font-semibold text-slate-900 truncate dark:text-slate-100">
                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                        </p>
                        <p class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                            <x-ui.icon name="calendar" class="w-3 h-3 text-slate-400" />
                            {{ $rdv->date }} à {{ substr((string) $rdv->heure, 0, 5) }}
                        </p>
                        <p class="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                            <x-ui.icon name="user" class="w-3 h-3 text-slate-400" />
                            {{ $rdv->client->name ?? 'Client' }}
                        </p>

                        @if($rdv->duree_reelle)
                            <p class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500">
                                <x-ui.icon name="clock" class="w-3 h-3" />
                                {{ $rdv->duree_reelle }} min
                            </p>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state title="Aucun historique" message="Votre historique de missions apparaîtra ici." icon="🧾" />
                @endforelse
            </div>
        </x-ui.card>
    </div>
</section>
