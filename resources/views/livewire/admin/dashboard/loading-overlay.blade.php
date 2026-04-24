<div wire:loading.flex
     wire:target="filtreEmploye,filtreZone,mettreAJourStats,chargerRdvs"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/30 backdrop-blur-sm">

    <div class="rounded-3xl border border-slate-200 bg-white px-6 py-5 shadow-2xl">
        <div class="flex items-center gap-4">
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600"></div>

            <div>
                <p class="font-black text-slate-900">Mise à jour du dashboard</p>
                <p class="text-sm text-slate-500">Les données sont en cours de recalcul...</p>
            </div>
        </div>
    </div>
</div>