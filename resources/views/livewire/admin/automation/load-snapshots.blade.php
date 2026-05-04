<section class="grid gap-6 xl:grid-cols-2">
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-600">
                    Capacité interne
                </p>
                <h2 class="mt-1 text-xl font-black text-slate-900">
                    Charge équipes
                </h2>
            </div>

            <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-black text-purple-700 ring-1 ring-purple-100">
                {{ $fieldTeamSnapshots->count() }} snapshots
            </span>
        </div>

        <div class="mt-5 space-y-3">
            @forelse($fieldTeamSnapshots as $snapshot)
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-black text-slate-900">
                                {{ $snapshot->fieldTeam->name ?? 'Équipe' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $snapshot->planned_segments_count ?? 0 }} segments • {{ $snapshot->planned_minutes ?? 0 }} min • {{ $snapshot->assigned_members_count ?? 0 }} membres
                            </p>
                        </div>

                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-700 ring-1 ring-slate-200">
                            {{ number_format((float) ($snapshot->load_ratio ?? 0), 0) }}%
                        </span>
                    </div>
                </div>
            @empty
                <p class="rounded-2xl bg-slate-50 p-5 text-center text-sm text-slate-500">
                    Aucun snapshot équipe pour cette date.
                </p>
            @endforelse
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-orange-600">
                    Capacité partenaires
                </p>
                <h2 class="mt-1 text-xl font-black text-slate-900">
                    Charge partenaires
                </h2>
            </div>

            <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700 ring-1 ring-orange-100">
                {{ $partnerSnapshots->count() }} snapshots
            </span>
        </div>

        <div class="mt-5 space-y-3">
            @forelse($partnerSnapshots as $snapshot)
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-black text-slate-900">
                                {{ $snapshot->servicePartner->name ?? 'Partenaire' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $snapshot->planned_segments_count ?? 0 }} segments • {{ $snapshot->planned_minutes ?? 0 }} min
                            </p>
                        </div>

                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-700 ring-1 ring-slate-200">
                            {{ number_format((float) ($snapshot->load_ratio ?? 0), 0) }}%
                        </span>
                    </div>
                </div>
            @empty
                <p class="rounded-2xl bg-slate-50 p-5 text-center text-sm text-slate-500">
                    Aucun snapshot partenaire pour cette date.
                </p>
            @endforelse
        </div>
    </div>
</section>
