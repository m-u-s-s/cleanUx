<x-page-shell
    title="📊 SLA & qualité opérationnelle"
    subtitle="Suivez la ponctualité, les litiges, les replanifications et la satisfaction client.">

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Du</label>
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Au</label>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="$set('dateFrom', ''); $set('dateTo', '')"
                    class="rounded-xl border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-quality-kpi-card title="Ponctualité" value="{{ $metrics['punctuality_rate'] }}%" subtitle="Missions à l’heure" />
        <x-quality-kpi-card title="Score qualité moyen" value="{{ $metrics['quality_score_avg'] }}/100" subtitle="Missions terminées" />
        <x-quality-kpi-card title="CSAT" value="{{ $metrics['csat_rate'] }}%" subtitle="Notes 4/5 ou 5/5" />
        <x-quality-kpi-card title="Note moyenne" value="{{ $metrics['avg_rating'] }}/5" subtitle="Feedback client" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
            <h3 class="font-semibold text-slate-900">📅 Réservations</h3>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Total RDV</p>
                    <p class="text-2xl font-bold text-slate-900">{{ $metrics['bookings_total'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Replanifications</p>
                    <p class="text-2xl font-bold text-blue-700">{{ $metrics['replanning_count'] }}</p>
                    <p class="text-xs text-slate-500">{{ $metrics['replanning_rate'] }}%</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Annulés / refusés</p>
                    <p class="text-2xl font-bold text-red-700">{{ $metrics['bookings_cancelled'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">No-show / annulé mission</p>
                    <p class="text-2xl font-bold text-amber-700">{{ $metrics['no_show_rate'] }}%</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
            <h3 class="font-semibold text-slate-900">⚠️ Litiges</h3>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Total litiges</p>
                    <p class="text-2xl font-bold text-slate-900">{{ $metrics['claims_total'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Litiges ouverts</p>
                    <p class="text-2xl font-bold text-red-700">{{ $metrics['claims_open'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Résolus</p>
                    <p class="text-2xl font-bold text-emerald-700">{{ $metrics['claims_resolved'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4">
                    <p class="text-slate-500">Temps résolution moyen</p>
                    <p class="text-2xl font-bold text-blue-700">{{ $metrics['avg_claim_resolution_hours'] }}h</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h3 class="font-semibold text-slate-900 mb-4">🧭 Missions</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 border p-4">
                <p class="text-slate-500">Missions totales</p>
                <p class="text-2xl font-bold text-slate-900">{{ $metrics['missions_total'] }}</p>
            </div>

            <div class="rounded-xl bg-slate-50 border p-4">
                <p class="text-slate-500">Missions terminées</p>
                <p class="text-2xl font-bold text-emerald-700">{{ $metrics['missions_completed'] }}</p>
            </div>

            <div class="rounded-xl bg-slate-50 border p-4">
                <p class="text-slate-500">Feedbacks reçus</p>
                <p class="text-2xl font-bold text-indigo-700">{{ $metrics['feedback_total'] }}</p>
            </div>
        </div>
    </div>
</x-page-shell>