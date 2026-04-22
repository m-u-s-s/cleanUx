<div class="space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Coordination chantier</h1>
        <p class="text-sm text-slate-600">Vision chef d’équipe sur les lots multi-jours et la progression des segments terrain.</p>
    </div>

    <div class="space-y-4">
        @forelse($leadBatches as $batch)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $batch->reference }}</div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $batch->name }}</h2>
                        <p class="text-sm text-slate-600">
                            {{ $batch->organizationAccount->name ?? 'Sans compte' }} · {{ $batch->organizationSite->name ?? 'Sans site' }}
                        </p>
                    </div>
                    <div class="text-sm text-slate-600">
                        {{ optional($batch->starts_on)->format('d/m/Y') }} → {{ optional($batch->ends_on)->format('d/m/Y') }}
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($batch->days as $day)
                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-slate-900">{{ $day->service_date?->format('d/m/Y') }}</div>
                                <div class="text-xs rounded-full px-2 py-1 bg-white border border-slate-200 text-slate-600">{{ $day->status }}</div>
                            </div>
                            <div class="mt-3 text-sm text-slate-600">Segments : {{ $day->segments->count() }}</div>
                            <div class="mt-2 space-y-2">
                                @foreach($day->segments as $segment)
                                    <div class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm">
                                        <div class="font-medium text-slate-900">{{ $segment->title }}</div>
                                        <div class="text-slate-500">{{ $segment->status }} · Équipe {{ $segment->crew_size }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                Aucun chantier coordonné pour le moment.
            </div>
        @endforelse
    </div>
</div>
