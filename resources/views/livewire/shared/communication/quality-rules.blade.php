{{-- Même défaut que le héros : `.dark h2 { color: #f1f5f9 }` sur une carte restée blanche. --}}
<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-950 dark:text-slate-100">Règles de traitement recommandées</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Chaque message important doit être visible, priorisé et traçable.
            </p>
        </div>

        <span class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            Process qualité
        </span>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">1. Prioriser</p>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                Identifier les alertes urgentes : incident terrain, paiement, client bloqué.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">2. Répondre</p>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                Envoyer une réponse claire au client, employé ou admin concerné.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">3. Tracer</p>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                Garder une trace dans les logs, notifications ou historiques.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">4. Clôturer</p>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                Marquer le problème comme résolu pour éviter les doublons.
            </p>
        </div>
    </div>
</section>
