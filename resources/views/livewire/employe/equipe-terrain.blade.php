<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Mon équipe terrain</h1>
        <p class="mt-1 text-sm text-slate-600">Vision chef d’équipe / membre d’équipe pour les missions groupées et les comptes professionnels.</p>
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-900">Équipes que je pilote</h2><span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">{{ $ledTeams->count() }}</span></div>
            <div class="mt-4 space-y-3">@forelse($ledTeams as $team)<div class="rounded-2xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-4"><div><p class="font-semibold text-slate-900">{{ $team->name }}</p><p class="text-xs text-slate-500 mt-1">{{ $team->serviceZone->name ?? 'Zone non définie' }} · {{ $team->organizationAccount->name ?? 'Compte générique' }}</p></div><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Lead</span></div><div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600"><span class="rounded-full bg-slate-100 px-3 py-1">{{ $team->activeMembers->count() }} membre(s)</span><span class="rounded-full bg-slate-100 px-3 py-1">Capacité {{ $team->max_concurrent_missions ?? '—' }}</span>@if($team->servicePartner)<span class="rounded-full bg-amber-100 px-3 py-1 text-amber-700">Partenaire {{ $team->servicePartner->name }}</span>@endif</div></div>@empty<p class="text-sm text-slate-500">Tu n’es encore lead d’aucune équipe terrain.</p>@endforelse</div>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-900">Équipes où je suis membre</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $memberTeams->count() }}</span></div>
            <div class="mt-4 space-y-3">@forelse($memberTeams as $team)<div class="rounded-2xl border border-slate-200 p-4"><p class="font-semibold text-slate-900">{{ $team->name }}</p><p class="text-xs text-slate-500 mt-1">Lead : {{ $team->teamLead->name ?? 'Non défini' }} · {{ $team->serviceZone->name ?? 'Zone non définie' }}</p></div>@empty<p class="text-sm text-slate-500">Tu n’es membre d’aucune équipe terrain active.</p>@endforelse</div>
        </section>
    </div>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-900">Missions d’équipe actives</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $activeTeamAssignments->count() }}</span></div>
        <div class="mt-4 space-y-3">@forelse($activeTeamAssignments as $assignment)<div class="rounded-2xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><div><p class="font-semibold text-slate-900">{{ $assignment->fieldTeam->name }}</p><p class="text-xs text-slate-500 mt-1">Mission #{{ $assignment->mission_id }} · {{ $assignment->mission->organizationAccount->name ?? 'Compte standard' }}</p></div><span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">{{ strtoupper($assignment->assignment_status) }}</span></div><div class="mt-3 text-sm text-slate-600 space-y-1"><p>Client : {{ $assignment->mission->rendezVous->client->name ?? '—' }}</p><p>Site : {{ $assignment->mission->organizationSite->name ?? $assignment->mission->rendezVous->location_display }}</p><p>Planifié : {{ optional($assignment->mission->planned_start_at)->format('d/m/Y H:i') ?? '—' }}</p></div></div>@empty<p class="text-sm text-slate-500">Aucune mission d’équipe active pour le moment.</p>@endforelse</div>
    </section>
</div>
