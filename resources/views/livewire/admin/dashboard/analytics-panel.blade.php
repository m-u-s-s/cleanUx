<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Analytics</p>
            <h3 class="text-xl font-black text-slate-900">Performance métier</h3>
            <p class="text-sm text-slate-500">Services, villes, durées et performance employés.</p>
        </div>

        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
            Données opérationnelles
        </span>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Feedback reçu</p>
            <p class="mt-2 text-3xl font-black text-blue-700">{{ $feedbackRate ?? 0 }}%</p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Durée estimée</p>
            <p class="mt-2 text-2xl font-black text-slate-900">
                {{ $dureeStats['avg_estimated'] ? $dureeStats['avg_estimated'] . ' min' : '—' }}
            </p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Durée réelle</p>
            <p class="mt-2 text-2xl font-black text-slate-900">
                {{ $dureeStats['avg_real'] ? $dureeStats['avg_real'] . ' min' : '—' }}
            </p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Écart moyen</p>
            <p class="mt-2 text-2xl font-black {{ ($dureeStats['avg_gap'] ?? 0) > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                @if(!is_null($dureeStats['avg_gap']))
                    {{ $dureeStats['avg_gap'] > 0 ? '+' : '' }}{{ $dureeStats['avg_gap'] }} min
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h4 class="mb-4 font-black text-slate-900">🧼 Services les plus demandés</h4>

            <div class="space-y-3">
                @forelse($topServices as $service)
                    <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm">
                        <span class="text-sm font-semibold text-slate-700">
                            {{ ucfirst(str_replace('_', ' ', $service->label ?? 'Service')) }}
                        </span>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">
                            {{ $service->total }}
                        </span>
                    </div>
                @empty
                    <x-empty-state title="Aucun service" message="Les services les plus demandés apparaîtront ici." icon="🧼" />
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h4 class="mb-4 font-black text-slate-900">📍 Villes les plus demandées</h4>

            <div class="space-y-3">
                @forelse($topVilles as $ville)
                    <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm">
                        <span class="text-sm font-semibold text-slate-700">{{ $ville->ville }}</span>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700">
                            {{ $ville->total }}
                        </span>
                    </div>
                @empty
                    <x-empty-state title="Aucune ville" message="Les villes les plus demandées apparaîtront ici." icon="📍" />
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h4 class="mb-4 font-black text-slate-900">🧑‍💼 Performance employés</h4>

            <div class="space-y-3">
                @forelse($performanceEmployes as $item)
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-900">{{ $item['employe']->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $item['missions_terminees'] }} mission(s) terminée(s)
                                </p>
                            </div>

                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                {{ !is_null($item['avg_note']) ? $item['avg_note'] . '/5' : '—' }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm text-slate-600">
                            Écart moyen :
                            <strong>
                                {{ !is_null($item['avg_gap']) ? ($item['avg_gap'] > 0 ? '+' : '') . $item['avg_gap'] . ' min' : '—' }}
                            </strong>
                        </p>
                    </div>
                @empty
                    <x-empty-state title="Aucune performance" message="Les performances employés apparaîtront ici." icon="🧑‍💼" />
                @endforelse
            </div>
        </div>
    </div>
</div>