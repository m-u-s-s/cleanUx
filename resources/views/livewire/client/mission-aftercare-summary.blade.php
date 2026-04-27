<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-6">
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">🧾 Résumé après intervention</h3>
            <p class="text-sm text-slate-500">
                Photos, checklist et résumé de la mission terminée.
            </p>
        </div>

        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            <p class="font-semibold">Score qualité</p>
            <p class="text-2xl font-bold">
                {{ $mission->quality_score ?? '—' }}/100
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-slate-500">Employé</p>
            <p class="mt-1 font-semibold text-slate-900">
                {{ $mission->leadEmployee?->name ?? '—' }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-slate-500">Début réel</p>
            <p class="mt-1 font-semibold text-slate-900">
                {{ optional($mission->actual_start_at)->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-slate-500">Fin réelle</p>
            <p class="mt-1 font-semibold text-slate-900">
                {{ optional($mission->actual_end_at)->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="font-semibold text-slate-900">📸 Avant</h4>
                <span class="text-xs text-slate-500">{{ $beforePhotos->count() }} photo(s)</span>
            </div>

            @if($beforePhotos->count())
                <div class="grid grid-cols-2 gap-3">
                    @foreach($beforePhotos as $photo)
                        <div class="rounded-xl overflow-hidden border bg-slate-50">
                            <img
                                src="{{ asset('storage/'.$photo->path) }}"
                                alt="Photo avant mission"
                                class="h-36 w-full object-cover">
                            @if($photo->caption)
                                <p class="p-2 text-xs text-slate-600">{{ $photo->caption }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500">Aucune photo avant disponible.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="font-semibold text-slate-900">✨ Après</h4>
                <span class="text-xs text-slate-500">{{ $afterPhotos->count() }} photo(s)</span>
            </div>

            @if($afterPhotos->count())
                <div class="grid grid-cols-2 gap-3">
                    @foreach($afterPhotos as $photo)
                        <div class="rounded-xl overflow-hidden border bg-slate-50">
                            <img
                                src="{{ asset('storage/'.$photo->path) }}"
                                alt="Photo après mission"
                                class="h-36 w-full object-cover">
                            @if($photo->caption)
                                <p class="p-2 text-xs text-slate-600">{{ $photo->caption }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500">Aucune photo après disponible.</p>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="font-semibold text-slate-900">✅ Checklist validée</h4>

            @if($checklist)
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                    {{ $checklist->completion_rate ?? 0 }}% complété
                </span>
            @endif
        </div>

        @if($checklist && $checklist->items->count())
            <div class="space-y-2">
                @foreach($checklist->items as $item)
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800">
                                {{ $item->label }}
                            </p>

                            @if($item->notes)
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $item->notes }}
                                </p>
                            @endif
                        </div>

                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                            {{ $item->status === 'completed'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-amber-100 text-amber-700' }}">
                            {{ $item->status === 'completed' ? 'Validé' : 'Non validé' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-500">
                Aucune checklist disponible pour cette mission.
            </p>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
        <h4 class="font-semibold text-slate-900">📝 Résumé intervention</h4>

        @if($report)
            <p class="text-sm text-slate-700">
                {{ $report->summary ?? 'Rapport généré sans résumé détaillé.' }}
            </p>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                <div class="rounded-xl bg-white border p-3">
                    <p class="text-slate-500">Photos avant</p>
                    <p class="font-semibold text-slate-900">{{ $report->before_photos_count ?? 0 }}</p>
                </div>

                <div class="rounded-xl bg-white border p-3">
                    <p class="text-slate-500">Photos après</p>
                    <p class="font-semibold text-slate-900">{{ $report->after_photos_count ?? 0 }}</p>
                </div>

                <div class="rounded-xl bg-white border p-3">
                    <p class="text-slate-500">Incidents</p>
                    <p class="font-semibold text-slate-900">{{ $report->incident_count ?? 0 }}</p>
                </div>

                <div class="rounded-xl bg-white border p-3">
                    <p class="text-slate-500">Validation client</p>
                    <p class="font-semibold text-slate-900">
                        {{ $report->client_validation ?? '—' }}
                    </p>
                </div>
            </div>
        @else
            <p class="text-sm text-slate-500">
                Le rapport sera disponible après clôture complète de la mission.
            </p>
        @endif
    </div>

    @if($incidents->count())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 space-y-3">
            <h4 class="font-semibold text-red-800">⚠️ Incident(s) signalé(s)</h4>

            @foreach($incidents as $incident)
                <div class="rounded-xl bg-white border border-red-100 p-3 text-sm">
                    <p class="font-medium text-red-800">{{ $incident->title }}</p>
                    @if($incident->description)
                        <p class="mt-1 text-red-700">{{ $incident->description }}</p>
                    @endif
                    <p class="mt-1 text-xs text-red-500">
                        Gravité : {{ $incident->severity }} — Statut : {{ $incident->status }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>