<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900">Équipes où je suis membre</h2>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $memberTeams->count() }}</span>
    </div>

    <div class="mt-4 space-y-3">
        @forelse($memberTeams as $team)
            <div class="rounded-2xl border border-slate-200 p-4">
                <p class="font-semibold text-slate-900">{{ $team->name }}</p>
                <p class="text-xs text-slate-500 mt-1">Lead : {{ $team->teamLead->name ?? 'Non défini' }} · {{ $team->serviceZone->name ?? 'Zone non définie' }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-500">Tu n’es membre d’aucune équipe terrain active.</p>
        @endforelse
    </div>
</section>
