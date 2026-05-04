<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900">Équipes que je pilote</h2>
        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">{{ $ledTeams->count() }}</span>
    </div>

    <div class="mt-4 space-y-3">
        @forelse($ledTeams as $team)
            <div class="rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $team->name }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $team->serviceZone->name ?? 'Zone non définie' }} · {{ $team->organizationAccount->name ?? 'Compte générique' }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Lead</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    <span class="rounded-full bg-slate-100 px-3 py-1">{{ $team->activeMembers->count() }} membre(s)</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1">Capacité {{ $team->max_concurrent_missions ?? '—' }}</span>
                    @if($team->servicePartner)
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-700">Partenaire {{ $team->servicePartner->name }}</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Tu n’es encore lead d’aucune équipe terrain.</p>
        @endforelse
    </div>
</section>
