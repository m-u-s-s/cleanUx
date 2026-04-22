<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-semibold text-slate-900">Qualité & incidents</h2>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <select wire:model.live="incidentStatus" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <option value="">Tous statuts incidents</option>
                <option value="open">Open</option>
                <option value="in_review">In review</option>
                <option value="resolved">Resolved</option>
                <option value="dismissed">Dismissed</option>
            </select>

            <select wire:model.live="incidentSeverity" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <option value="">Toutes sévérités</option>
                <option value="low">Faible</option>
                <option value="medium">Moyen</option>
                <option value="high">Élevé</option>
                <option value="critical">Critique</option>
            </select>

            <select wire:model.live="missionQualityStatus" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <option value="">Tous statuts qualité</option>
                <option value="excellent">Excellent</option>
                <option value="good">Good</option>
                <option value="warning">Warning</option>
                <option value="critical">Critical</option>
            </select>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Scores employés</h3>
            <div class="mt-4 space-y-3">
                @foreach($employeeScores as $row)
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ $row->employee_name }}</p>
                            <p class="text-sm text-slate-500">{{ $row->missions_count }} mission(s)</p>
                        </div>
                        <div class="text-right font-semibold text-slate-900">
                            {{ number_format($row->avg_score, 1) }}/100
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Scores équipe mission</h3>
            <div class="mt-4 space-y-3">
                @foreach($teamScores as $row)
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ $row->booking_reference }}</p>
                            <p class="text-sm text-slate-500">{{ $row->members_count }} membre(s)</p>
                        </div>
                        <div class="text-right font-semibold text-slate-900">
                            {{ number_format($row->team_score, 1) }}/100
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Incidents</h3>

        <div class="mt-4 space-y-3">
            @foreach($incidents as $incident)
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $incident->title }}</p>
                            <p class="text-sm text-slate-500">
                                Mission {{ $incident->mission?->rendezVous?->booking_reference ?? '#'.$incident->mission_id }}
                            </p>
                        </div>

                        <div class="text-right text-sm">
                            <p class="font-medium text-slate-800">{{ $incident->severity }}</p>
                            <p class="text-slate-500">{{ $incident->status }}</p>
                        </div>
                    </div>

                    @if($incident->description)
                        <p class="mt-3 text-sm text-slate-700">{{ $incident->description }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $incidents->links() }}
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Dernières missions qualité</h3>

        <div class="mt-4 space-y-3">
            @foreach($missions as $mission)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                    <div>
                        <p class="font-medium text-slate-900">{{ $mission->rendezVous?->booking_reference ?? 'Mission #'.$mission->id }}</p>
                        <p class="text-sm text-slate-500">{{ $mission->leadEmployee?->name ?? '—' }}</p>
                    </div>

                    <div class="text-right">
                        <p class="font-semibold text-slate-900">{{ $mission->quality_score ?? '—' }}/100</p>
                        <p class="text-sm text-slate-500">{{ $mission->quality_status }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>