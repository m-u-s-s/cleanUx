<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
        @foreach($jours as $jour)
            <section class="flex min-h-[18rem] flex-col rounded-[1.75rem] border p-4 shadow-sm {{ $jour['is_focus'] ? 'border-sky-300 bg-sky-50/50' : 'border-slate-200 bg-slate-50/70' }} {{ $jour['is_today'] ? 'ring-2 ring-indigo-100' : '' }}">
                <div class="mb-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-black {{ $jour['is_focus'] ? 'text-sky-900' : 'text-slate-900' }}">
                                {{ $jour['short_label'] }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $jour['rdvs']->count() }} intervention(s) • {{ $jour['total_hours'] }} h
                            </p>
                        </div>
                        @if($jour['is_today'])
                            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-black text-indigo-700">Aujourd’hui</span>
                        @elseif($jour['is_focus'])
                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-black text-sky-700">Focus</span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-bold">
                        <span class="rounded-full bg-white px-2.5 py-1 text-slate-600">
                            {{ $jour['active_count'] }} actives
                        </span>
                        @if($jour['urgent_count'] > 0)
                            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-rose-700">
                                {{ $jour['urgent_count'] }} urgente(s)
                            </span>
                        @endif
                        @if($jour['unassigned_count'] > 0)
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-700">
                                {{ $jour['unassigned_count'] }} sans employé
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex-1 space-y-3">
                    @forelse($jour['rdvs'] as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <div class="flex h-full items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white/70 p-4 text-center text-sm text-slate-500">
                            Aucune intervention sur ce créneau.
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
