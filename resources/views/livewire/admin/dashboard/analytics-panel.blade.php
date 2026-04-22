<div class="rounded border bg-white p-5 shadow space-y-5">
    <div>
        <h3 class="text-lg font-semibold text-slate-800">📈 Analytics métier avancées</h3>
        <p class="text-sm text-gray-500">Vue business sur les services, les villes, les durées et la performance.</p>
    </div>

    <div class="rounded border bg-white p-5 shadow space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-800">🧠 Recommandations automatiques</h3>
            <p class="text-sm text-gray-500">Aide à la décision basée sur la charge, les durées, les zones et les retours clients.</p>
        </div>

        <div class="space-y-3">
            @forelse($recommendations as $rec)
                @php
                    $classes = match($rec['level']) {
                        'danger' => 'bg-red-50 border-red-200 text-red-800',
                        'warning' => 'bg-orange-50 border-orange-200 text-orange-800',
                        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
                        default => 'bg-gray-50 border-gray-200 text-gray-800',
                    };
                @endphp

                <div class="rounded-lg border p-4 {{ $classes }}">
                    <p class="font-semibold">{{ $rec['title'] }}</p>
                    <p class="mt-1 text-sm">{{ $rec['message'] }}</p>
                </div>
            @empty
                <div class="text-sm italic text-gray-500">Aucune recommandation particulière pour le moment.</div>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Feedback reçu</p>
            <p class="text-2xl font-bold text-blue-700">{{ $feedbackRate }}%</p>
        </div>

        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Durée estimée moyenne</p>
            <p class="text-2xl font-bold text-slate-800">{{ $dureeStats['avg_estimated'] ? $dureeStats['avg_estimated'] . ' min' : '—' }}</p>
        </div>

        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Durée réelle moyenne</p>
            <p class="text-2xl font-bold text-slate-800">{{ $dureeStats['avg_real'] ? $dureeStats['avg_real'] . ' min' : '—' }}</p>
        </div>

        <div class="rounded-lg border bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Écart moyen</p>
            <p class="text-2xl font-bold {{ ($dureeStats['avg_gap'] ?? 0) > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                @if(!is_null($dureeStats['avg_gap']))
                    {{ $dureeStats['avg_gap'] > 0 ? '+' : '' }}{{ $dureeStats['avg_gap'] }} min
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-xl border bg-gray-50 p-4">
            <h4 class="mb-3 font-semibold text-slate-800">🧼 Services les plus demandés</h4>
            <div class="space-y-2">
                @forelse($topServices as $service)
                    <div class="flex items-center justify-between rounded border bg-white p-2">
                        <span class="text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $service->service_type ?? 'Service')) }}</span>
                        <span class="text-sm font-semibold text-slate-800">{{ $service->total }}</span>
                    </div>
                @empty
                    <p class="text-sm italic text-gray-500">Aucune donnée disponible.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border bg-gray-50 p-4">
            <h4 class="mb-3 font-semibold text-slate-800">📍 Villes les plus demandées</h4>
            <div class="space-y-2">
                @forelse($topVilles as $ville)
                    <div class="flex items-center justify-between rounded border bg-white p-2">
                        <span class="text-sm text-gray-700">{{ $ville->ville }}</span>
                        <span class="text-sm font-semibold text-slate-800">{{ $ville->total }}</span>
                    </div>
                @empty
                    <p class="text-sm italic text-gray-500">Aucune donnée disponible.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border bg-gray-50 p-4">
            <h4 class="mb-3 font-semibold text-slate-800">🧑‍💼 Performance employés</h4>
            <div class="space-y-2">
                @forelse($performanceEmployes as $item)
                    <div class="rounded border bg-white p-3">
                        <p class="font-semibold text-gray-800">{{ $item['employe']->name }}</p>
                        <p class="text-sm text-gray-600">{{ $item['missions_terminees'] }} mission(s) terminée(s)</p>
                        <p class="text-sm text-gray-600">Écart moyen : {{ !is_null($item['avg_gap']) ? ($item['avg_gap'] > 0 ? '+' : '') . $item['avg_gap'] . ' min' : '—' }}</p>
                        <p class="text-sm text-gray-600">Note moyenne : {{ !is_null($item['avg_note']) ? $item['avg_note'] . '/5' : '—' }}</p>
                    </div>
                @empty
                    <p class="text-sm italic text-gray-500">Aucune donnée disponible.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
