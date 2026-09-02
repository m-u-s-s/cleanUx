{{--
    REPRIS DE « MATCHING INSIGHTS », et c'est le panneau qui meritait de survivre : ces poids sont
    lus par `MatchingScoreEngine::weights()`, le scoreur que `CandidateFinder` emploie vraiment.
    Le panneau decrit donc le dispatch, pas un moteur voisin.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Poids du score</h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
        Ce que le moteur lit pour départager deux candidats à distance égale. Réglés par
        <code>config/matching.php</code> et les variables <code>MATCHING_W_*</code>.
    </p>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @forelse ($poids as $cle => $valeur)
            <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $cle }}</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">
                    {{ $valeur }}<span class="text-sm font-normal text-slate-500">%</span>
                </p>
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucun poids configuré.</p>
        @endforelse
    </div>

    @if ($poids !== [])
        <p class="mt-3 text-xs text-slate-500">Total : {{ array_sum($poids) }} %</p>
    @endif
</section>
