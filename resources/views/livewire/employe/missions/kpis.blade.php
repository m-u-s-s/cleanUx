        <section class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-7">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Total</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-amber-700">À confirmer</p>
                <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['a_confirmer'] ?? 0 }}</p>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-blue-700">À faire</p>
                <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['a_faire'] ?? 0 }}</p>
            </div>

            <div class="rounded-3xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-indigo-700">En route</p>
                <p class="mt-2 text-3xl font-black text-indigo-900">{{ $stats['en_route'] ?? 0 }}</p>
            </div>

            <div class="rounded-3xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-sky-700">Sur place</p>
                <p class="mt-2 text-3xl font-black text-sky-900">{{ $stats['sur_place'] ?? 0 }}</p>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-emerald-700">Terminées</p>
                <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['terminees'] ?? 0 }}</p>
            </div>

            <div class="rounded-3xl border border-purple-200 bg-purple-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-purple-700">Zones</p>
                <p class="mt-2 text-3xl font-black text-purple-900">{{ $stats['zone_count'] ?? 0 }}</p>
            </div>
        </section>
