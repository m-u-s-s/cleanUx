@php
    $summary = $this->executiveSummary;
@endphp

<div class="rounded-3xl border p-6 shadow-sm
    {{ $summary['status'] === 'stable'
        ? 'border-emerald-200 bg-emerald-50'
        : 'border-amber-200 bg-amber-50' }}">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide
                {{ $summary['status'] === 'stable' ? 'text-emerald-700' : 'text-amber-700' }}">
                Synthèse exécutive
            </p>

            <h3 class="mt-1 text-2xl font-black text-slate-900">
                {{ $summary['title'] }}
            </h3>

            <p class="mt-2 max-w-3xl text-sm leading-relaxed
                {{ $summary['status'] === 'stable' ? 'text-emerald-800' : 'text-amber-800' }}">
                {{ $summary['message'] }}
            </p>
        </div>

        <div class="text-4xl">
            {{ $summary['status'] === 'stable' ? '✅' : '⚠️' }}
        </div>
    </div>
</div>