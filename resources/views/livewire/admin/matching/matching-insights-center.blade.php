<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Matching v2</p>
                <h1 class="text-2xl font-black text-slate-900">Insights & contrôle du dispatch</h1>
                <p class="text-sm text-slate-500">
                    Algorithme :
                    <span class="font-mono font-bold">{{ $config['version'] }}</span>
                    @if($config['enabled'])
                        <span class="text-emerald-600 font-semibold">activé</span>
                    @else
                        <span class="text-red-600 font-semibold">désactivé</span>
                    @endif
                    @if($config['shadow_mode'])
                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                            Shadow mode
                        </span>
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Décisions totales</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['decisions_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Aujourd'hui</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['decisions_today']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Score moyen choisi</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['avg_top_score'], 1, ',', ' ') }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Candidats moyen</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['avg_candidates'], 1, ',', ' ') }}</p>
            </div>
        </div>

        {{-- Poids actuels --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold uppercase text-slate-500">Poids actuels du score</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mt-3">
                @foreach($weights as $key => $value)
                    <div class="rounded-xl bg-slate-50 border p-3">
                        <p class="text-xs text-slate-500">{{ $key }}</p>
                        <p class="text-2xl font-bold text-slate-900">{{ $value }}<span class="text-xs text-slate-400">%</span></p>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-400 mt-3">
                Les poids sont configurés via <code class="font-mono">config/matching.php</code> et les variables d'environnement (<code>MATCHING_W_*</code>). Total :
                {{ collect($weights)->sum() }}%
            </p>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-2 border-b">
            @foreach([
                'recent_decisions' => 'Décisions récentes',
                'provider_metrics' => 'Métriques providers',
                'simulator' => 'Simuler un dispatch',
            ] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-2 text-sm font-semibold border-b-2 {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($currentView === 'decisions')
            <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Booking</th>
                            <th class="px-4 py-2 text-left">Provider choisi</th>
                            <th class="px-4 py-2 text-left">Score</th>
                            <th class="px-4 py-2 text-left">2ème score</th>
                            <th class="px-4 py-2 text-left">Candidats</th>
                            <th class="px-4 py-2 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $d)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs">
                                    #{{ $d->booking?->booking_reference ?? $d->booking_id }}
                                </td>
                                <td class="px-4 py-2 font-semibold">{{ $d->selectedProvider?->name ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                                        {{ number_format((float) $d->selected_score, 1, ',', ' ') }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-slate-500">
                                    {{ $d->runner_up_score !== null ? number_format((float) $d->runner_up_score, 1, ',', ' ') : '—' }}
                                </td>
                                <td class="px-4 py-2">{{ $d->candidates_count }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $d->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                Aucune décision enregistrée.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $items->links() }}</div>
            </div>
        @elseif($currentView === 'metrics')
            <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Période</th>
                            <th class="px-4 py-2 text-left">Acceptance</th>
                            <th class="px-4 py-2 text-left">Completion</th>
                            <th class="px-4 py-2 text-left">Rép. moy.</th>
                            <th class="px-4 py-2 text-left">Rating 30j</th>
                            <th class="px-4 py-2 text-left">Calculé le</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $m)
                            <tr>
                                <td class="px-4 py-2 font-semibold">{{ $m->provider?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">
                                    {{ $m->period_start?->format('d/m') }} → {{ $m->period_end?->format('d/m') }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ $m->acceptance_rate !== null ? round((float) $m->acceptance_rate * 100) . '%' : '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ $m->completion_rate !== null ? round((float) $m->completion_rate * 100) . '%' : '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ $m->avg_response_seconds ? round($m->avg_response_seconds) . 's' : '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ $m->rating_avg_window ? number_format((float) $m->rating_avg_window, 1, ',', ' ') . ' (' . $m->rating_count_window . ')' : '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $m->computed_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                Aucune métrique calculée — lancez <code class="font-mono">php artisan matching:refresh-metrics</code>.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $items->links() }}</div>
            </div>
        @else
            {{-- Simulator --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-bold text-slate-900">Simuler un dispatch</h2>
                    <p class="text-sm text-slate-500">
                        Voir comment le moteur classerait les providers pour un booking donné.
                        Aucune modification n'est faite en base.
                    </p>

                    <div>
                        <label class="text-sm font-semibold text-slate-700">ID du booking</label>
                        <input type="number" wire:model="simulateBookingId"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('simulateBookingId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button wire:click="simulate"
                            class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Lancer la simulation
                    </button>
                </div>

                <div class="lg:col-span-2">
                    @if($simulationResult)
                        <div class="rounded-2xl border bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-slate-900">
                                Résultats pour booking #{{ $simulationResult['booking_reference'] ?? $simulationResult['booking_id'] }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Zone {{ $simulationResult['service_zone_id'] }} · {{ $simulationResult['date'] }}
                            </p>

                            <div class="mt-4 space-y-3">
                                @forelse($simulationResult['candidates'] as $i => $c)
                                    <div class="rounded-xl border p-4 {{ $i === 0 ? 'border-emerald-300 bg-emerald-50' : '' }}">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                @if($i === 0)
                                                    <span class="inline-flex items-center rounded-full bg-emerald-200 px-2 py-0.5 text-xs font-bold text-emerald-900 mr-2">
                                                        #1 Choix
                                                    </span>
                                                @else
                                                    <span class="text-xs text-slate-400 font-semibold mr-2">#{{ $i + 1 }}</span>
                                                @endif
                                                <span class="font-bold">{{ $c['name'] }}</span>
                                            </div>
                                            <span class="text-lg font-black text-indigo-600">
                                                {{ number_format((float) $c['score'], 1, ',', ' ') }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mt-3">
                                            @foreach($c['components'] as $key => $comp)
                                                <div class="text-xs">
                                                    <p class="text-slate-500">{{ $key }}</p>
                                                    <p class="font-semibold text-slate-700">
                                                        {{ number_format($comp['raw'], 0) }}
                                                        <span class="text-slate-400">×{{ $comp['weight'] }}%</span>
                                                    </p>
                                                    <p class="text-indigo-600 font-bold">
                                                        = {{ number_format($comp['weighted'], 1, ',', ' ') }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-400 text-center py-6">
                                        Aucun candidat éligible pour ce booking.
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-10 text-center text-slate-400">
                            Entrez un ID de booking pour voir le ranking détaillé.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
