{{-- KPIS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ number_format($stats['total_hours'] ?? 0, 1, ',', ' ') }} h planifiées
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xl">📊</div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                        <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ $stats['confirme'] ?? 0 }} confirmés / {{ $stats['attente'] ?? 0 }} en attente
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">✅</div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                        <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-amber-700">
                            {{ $stats['sans_employe'] ?? 0 }} sans employé
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🚨</div>
                </div>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Couverture</p>
                        <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ $weekSummary['days_with_work'] ?? 0 }} jours chargés • {{ $weekSummary['entreprise_count'] ?? 0 }} RDV B2B
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🧭</div>
                </div>
            </div>
        </section>
