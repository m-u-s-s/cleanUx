<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">Qualité</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Checklists mission</h2>
        </div>

        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
            {{ $mission->checklists->count() }} checklist(s)
        </span>
    </div>

    @if($mission->checklists->isEmpty())
        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
            Aucune checklist n’est liée à cette mission.
        </div>
    @else
        <div class="mt-5 space-y-4">
            @foreach($mission->checklists as $checklist)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black text-slate-900">{{ $checklist->template_name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $checklist->items->count() }} tâche(s)</p>
                        </div>

                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-700 ring-1 ring-slate-200">
                            {{ $checklist->completion_rate ?? 0 }}%
                        </span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach($checklist->items as $item)
                            @php
                                $done = in_array($item->status, ['done', 'completed'], true);
                            @endphp

                            <div class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 {{ $done ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }}">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $done ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400' }} text-xs font-black">
                                        {{ $done ? '✓' : '•' }}
                                    </span>
                                    <span class="text-sm font-semibold {{ $done ? 'text-emerald-900' : 'text-slate-700' }}">
                                        {{ $item->label }}
                                        @if($item->is_required)
                                            <span class="text-rose-500">*</span>
                                        @endif
                                    </span>
                                </div>

                                <span class="text-xs font-bold {{ $done ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ $done ? 'Fait' : 'À faire' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
