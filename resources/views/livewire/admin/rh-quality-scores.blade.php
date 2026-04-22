<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-lg font-semibold text-slate-900">Score global employés</h3>

    <div class="mt-4 space-y-3">
        @foreach($employeeScores as $row)
            <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                <div>
                    <div class="font-medium text-slate-900">{{ $row->employee_name }}</div>
                    <div class="text-sm text-slate-500">{{ $row->missions_count }} mission(s)</div>
                </div>
                <div class="text-right">
                    <div class="font-semibold text-slate-900">{{ number_format($row->avg_score, 1) }}/100</div>
                </div>
            </div>
        @endforeach
    </div>
</div>