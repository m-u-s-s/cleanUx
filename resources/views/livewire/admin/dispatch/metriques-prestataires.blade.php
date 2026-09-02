{{--
    REPRIS DE « MATCHING INSIGHTS ». `ProviderPerformanceCalculator` est injecte dans
    `MatchingScoreEngine` et rafraichi par une commande planifiee : ces chiffres alimentent
    vraiment le score du dispatch.
--}}
<section class="rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
    <div class="border-b border-slate-100 p-5 dark:border-white/5">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Métriques prestataires</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
            Ce qui alimente le score, rafraîchi par <code>matching:refresh-provider-metrics</code>.
        </p>
    </div>

    <div class="brio-table-cadre">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="p-4">Prestataire</th>
                    <th>Fin de période</th>
                    <th>Note</th>
                    <th>Acceptation</th>
                    <th>Complétion</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($metriques as $metrique)
                    <tr wire:key="metrique-{{ $metrique->id }}">
                        <td class="p-4 font-medium text-slate-900 dark:text-white">
                            {{ $metrique->provider?->name ?? '—' }}
                        </td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $metrique->period_end }}</td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $metrique->avg_rating ?? '—' }}</td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $metrique->acceptance_rate ?? '—' }}</td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $metrique->completion_rate ?? '—' }}</td>
                        <td class="text-slate-600 dark:text-slate-300">{{ $metrique->avg_response_seconds ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-sm text-slate-500">
                            Aucune métrique calculée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4">{{ $metriques->links() }}</div>
</section>
