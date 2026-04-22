<div class="grid gap-6 xl:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Score par pays</h3>
        <div class="mt-4 space-y-3">
            @foreach($byCountry as $row)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                    <div>
                        <div class="font-medium text-slate-900">{{ $row->label ?? '—' }}</div>
                        <div class="text-sm text-slate-500">{{ $row->missions_count }} mission(s)</div>
                    </div>
                    <div class="font-semibold text-slate-900">{{ number_format($row->avg_score, 1) }}/100</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Score par zone</h3>
        <div class="mt-4 space-y-3">
            @foreach($byZone as $row)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                    <div>
                        <div class="font-medium text-slate-900">{{ $row->label ?? '—' }}</div>
                        <div class="text-sm text-slate-500">{{ $row->missions_count }} mission(s)</div>
                    </div>
                    <div class="font-semibold text-slate-900">{{ number_format($row->avg_score, 1) }}/100</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Score par service</h3>
        <div class="mt-4 space-y-3">
            @foreach($byService as $row)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                    <div>
                        <div class="font-medium text-slate-900">{{ $row->label ?? '—' }}</div>
                        <div class="text-sm text-slate-500">{{ $row->missions_count }} mission(s)</div>
                    </div>
                    <div class="font-semibold text-slate-900">{{ number_format($row->avg_score, 1) }}/100</div>
                </div>
            @endforeach
        </div>
    </div>
</div>