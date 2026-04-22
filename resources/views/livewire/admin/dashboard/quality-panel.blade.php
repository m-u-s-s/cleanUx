<div class="cu-card p-5">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-800">🧪 Suivi qualité des missions</h3>
            <p class="text-sm text-gray-500">Contrôle des rapports, photos après intervention et écarts de durée.</p>
        </div>
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Missions sans rapport</p>
            <p class="text-2xl font-bold text-red-600">{{ $qualiteStats['sans_rapport'] }}</p>
        </div>

        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Missions sans photos après</p>
            <p class="text-2xl font-bold text-orange-600">{{ $qualiteStats['sans_photos_apres'] }}</p>
        </div>

        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Missions avec durée réelle</p>
            <p class="text-2xl font-bold text-emerald-600">{{ $qualiteStats['avec_duree_reelle'] }}</p>
        </div>
    </div>

    <div class="space-y-3">
        @forelse($qualiteMissions as $item)
            @php($rdv = $item['rdv'])

            <div class="rounded-lg border bg-gray-50 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $rdv->service_display_name ?: 'Service non précisé' }}</p>
                        <p class="text-sm text-gray-600">👤 {{ $rdv->client->name ?? '—' }} · 🧑‍💼 {{ $rdv->employe->name ?? '—' }}</p>
                        <p class="text-sm text-gray-600">📅 {{ $rdv->date }} à {{ $rdv->heure }}</p>

                        @if(!empty($suggestedEmployees))
                            <div class="mt-4">
                                <h4 class="mb-2 text-sm font-semibold text-slate-800">🧠 Suggestions automatiques</h4>

                                <div class="space-y-2">
                                    @foreach($suggestedEmployees as $suggestion)
                                        <div class="flex items-center justify-between rounded-lg border bg-gray-50 p-3">
                                            <div>
                                                <p class="font-medium text-gray-800">{{ $suggestion['name'] }}</p>
                                                <p class="text-xs text-gray-600">
                                                    {{ $suggestion['rdv_count'] }} mission(s) • {{ $suggestion['load_minutes'] }} min planifiées
                                                    @if($suggestion['same_city_bonus'] < 0)
                                                        • même ville
                                                    @endif
                                                </p>
                                            </div>

                                            <button wire:click="appliquerSuggestionEmploye({{ $suggestion['id'] }})" type="button" class="rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700">
                                                Choisir
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :status="$rdv->status" />
                        <x-priority-badge :priority="$rdv->priorite" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                    <div class="rounded border bg-white p-3">
                        <p class="text-gray-500">Rapport</p>
                        <p class="font-semibold {{ $item['has_report'] ? 'text-emerald-700' : 'text-red-600' }}">{{ $item['has_report'] ? 'Présent' : 'Manquant' }}</p>
                    </div>

                    <div class="rounded border bg-white p-3">
                        <p class="text-gray-500">Photos après</p>
                        <p class="font-semibold {{ $item['has_after_photos'] ? 'text-emerald-700' : 'text-orange-600' }}">{{ $item['has_after_photos'] ? 'Présentes' : 'Manquantes' }}</p>
                    </div>

                    <div class="rounded border bg-white p-3">
                        <p class="text-gray-500">Durée</p>
                        <p class="font-semibold text-slate-800">
                            Estimée : {{ $item['estimated'] ? $item['estimated'] . ' min' : '—' }}
                            <br>
                            Réelle : {{ $item['real'] ? $item['real'] . ' min' : '—' }}
                        </p>
                    </div>
                </div>

                @if(!is_null($item['difference']))
                    <div class="mt-3 text-sm">
                        @if($item['is_long_overrun'])
                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">+{{ $item['difference'] }} min par rapport à l’estimé</span>
                        @elseif($item['is_short_underrun'])
                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $item['difference'] }} min par rapport à l’estimé</span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Durée cohérente</span>
                        @endif
                    </div>
                @endif

                @if($rdv->commentaire_fin_mission)
                    <div class="mt-3 rounded border bg-white p-3 text-sm text-gray-700">
                        <span class="font-medium">Rapport employé :</span>
                        <p class="mt-1">{{ $rdv->commentaire_fin_mission }}</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-sm italic text-gray-500">Aucune donnée qualité disponible pour le moment.</div>
        @endforelse
    </div>
</div>
