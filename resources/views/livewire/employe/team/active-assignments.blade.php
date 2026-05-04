<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900">Missions d’équipe actives</h2>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $activeTeamAssignments->count() }}</span>
    </div>

    <div class="mt-4 space-y-3">
        @forelse($activeTeamAssignments as $assignment)
            <div class="rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $assignment->fieldTeam->name }}</p>
                        <p class="text-xs text-slate-500 mt-1">Mission #{{ $assignment->mission_id }} · {{ $assignment->mission->organizationAccount->name ?? 'Compte standard' }}</p>
                    </div>
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">{{ strtoupper($assignment->assignment_status) }}</span>
                </div>
                <div class="mt-3 text-sm text-slate-600 space-y-1">
                    <p>Client : {{ $assignment->mission->rendezVous->client->name ?? '—' }}</p>
                    <p>Site : {{ $assignment->mission->organizationSite->name ?? $assignment->mission->rendezVous->location_display }}</p>
                    <p>Planifié : {{ optional($assignment->mission->planned_start_at)->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucune mission d’équipe active pour le moment.</p>
        @endforelse
    </div>
</section>
