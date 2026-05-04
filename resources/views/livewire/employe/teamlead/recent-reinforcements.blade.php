<div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
    <h3 class="text-lg font-bold text-slate-900">Demandes de renfort récentes</h3>
    <div class="space-y-3">
        @forelse($reinforcementRequests as $request)
            <div class="rounded-xl border p-4">
                <p class="font-semibold text-slate-900">{{ $request->priority }} · {{ $request->status }}</p>
                <p class="text-sm text-slate-500">{{ $request->reason }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucune demande récente.</p>
        @endforelse
    </div>
</div>
