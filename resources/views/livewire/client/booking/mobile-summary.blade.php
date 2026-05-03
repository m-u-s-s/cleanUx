<div class="xl:hidden fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3 shadow-[0_-12px_30px_rgba(15,23,42,0.08)]">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimation</p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <p class="text-lg font-extrabold text-slate-900">{{ number_format((float) $devis_estime, 2, ',', ' ') }} €</p>
                <p class="text-xs text-slate-500">{{ $duree_estimee > 0 ? $duree_estimee . ' min' : 'Durée à confirmer' }}</p>
            </div>
        </div>

        <div class="text-right text-xs text-slate-500 shrink-0">
            <p class="font-semibold text-slate-700">Étape {{ $step }}/5</p>
            <p>{{ $selectedServiceLabel ?? ($services[$selected_service_identifier] ?? 'Service') }}</p>
        </div>
    </div>
</div>
