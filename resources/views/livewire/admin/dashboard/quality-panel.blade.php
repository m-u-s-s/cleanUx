<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">Qualité opérationnelle</p>
            <h3 class="text-xl font-black text-slate-900">Suivi qualité des missions</h3>
            <p class="text-sm text-slate-500">
                Rapports, photos après intervention et écarts de durée.
            </p>
        </div>

        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
            {{ $qualiteMissions->count() ?? 0 }} mission(s)
        </span>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-red-100 bg-red-50 p-5">
            <p class="text-sm font-semibold text-red-700">Missions sans rapport</p>
            <p class="mt-2 text-3xl font-black text-red-700">
                {{ $qualiteStats['sans_rapport'] ?? 0 }}
            </p>
            <p class="mt-1 text-xs text-red-600">
                À compléter par les employés.
            </p>
        </div>

        <div class="rounded-2xl border border-orange-100 bg-orange-50 p-5">
            <p class="text-sm font-semibold text-orange-700">Sans photos après</p>
            <p class="mt-2 text-3xl font-black text-orange-700">
                {{ $qualiteStats['sans_photos_apres'] ?? 0 }}
            </p>
            <p class="mt-1 text-xs text-orange-600">
                Contrôle visuel manquant.
            </p>
        </div>

        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
            <p class="text-sm font-semibold text-emerald-700">Avec durée réelle</p>
            <p class="mt-2 text-3xl font-black text-emerald-700">
                {{ $qualiteStats['avec_duree_reelle'] ?? 0 }}
            </p>
            <p class="mt-1 text-xs text-emerald-600">
                Données exploitables.
            </p>
        </div>
    </div>

    <div class="space-y-4">
        @forelse($qualiteMissions as $item)
            @php
                $rdv = $item['rdv'];
                $difference = $item['difference'];
                $durationTone = is_null($difference)
                    ? 'slate'
                    : ($item['is_long_overrun'] ? 'red' : ($item['is_short_underrun'] ? 'blue' : 'emerald'));
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="font-black text-slate-900">
                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-600">
                            👤 {{ $rdv->client->name ?? '—' }}
                            · 🧑‍💼 {{ $rdv->employe->name ?? '—' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            📅 {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                            · 🕒 {{ substr((string) $rdv->heure, 0, 5) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :status="$rdv->status" />
                        <x-priority-badge :priority="$rdv->priorite" />
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-xl border bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Rapport</p>

                        @if($item['has_report'])
                            <p class="mt-2 font-black text-emerald-700">Présent</p>
                        @else
                            <p class="mt-2 font-black text-red-600">Manquant</p>
                        @endif
                    </div>

                    <div class="rounded-xl border bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Photos après</p>

                        @if($item['has_after_photos'])
                            <p class="mt-2 font-black text-emerald-700">Présentes</p>
                        @else
                            <p class="mt-2 font-black text-orange-600">Manquantes</p>
                        @endif
                    </div>

                    <div class="rounded-xl border bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Durée</p>

                        <p class="mt-2 text-sm font-semibold text-slate-700">
                            Estimée : {{ $item['estimated'] ? $item['estimated'] . ' min' : '—' }}
                        </p>

                        <p class="text-sm font-semibold text-slate-700">
                            Réelle : {{ $item['real'] ? $item['real'] . ' min' : '—' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @if(!is_null($difference))
                        <span class="rounded-full px-3 py-1 text-xs font-black
                            {{ $durationTone === 'red' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : '' }}
                            {{ $durationTone === 'blue' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' : '' }}
                            {{ $durationTone === 'emerald' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : '' }}">
                            @if($item['is_long_overrun'])
                                +{{ $difference }} min par rapport à l’estimé
                            @elseif($item['is_short_underrun'])
                                {{ $difference }} min par rapport à l’estimé
                            @else
                                Durée cohérente
                            @endif
                        </span>
                    @else
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                            Durée non comparable
                        </span>
                    @endif

                    @if(!$item['has_report'])
                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700 ring-1 ring-red-200">
                            Rapport à compléter
                        </span>
                    @endif

                    @if(!$item['has_after_photos'])
                        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700 ring-1 ring-orange-200">
                            Photos à ajouter
                        </span>
                    @endif
                </div>

                @if($rdv->commentaire_fin_mission)
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Rapport employé
                        </p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-700">
                            {{ $rdv->commentaire_fin_mission }}
                        </p>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state title="Aucune donnée qualité" message="Les missions terminées avec données qualité apparaîtront ici." icon="🧪" />
        @endforelse
    </div>
</div>