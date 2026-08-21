{{--
    UN TITRE CLAIR SUR UNE SURFACE CLAIRE, EN MODE SOMBRE.

    `resources/css/base.css` applique `.dark h1, .dark h2, … { color: #f1f5f9 }` sans condition.
    Ce bloc, lui, était en `bg-white` sans une seule variante `dark:` : en sombre, le titre passait
    en blanc SUR DU BLANC — couleur calculée rgb(241,245,249) sur fond rgb(255,255,255), donc
    invisible. Le correctif n'est pas de repeindre le texte mais la SURFACE : la carte devient
    sombre, et le titre clair redevient juste.
--}}
<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                Communication Brio
            </div>

            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 md:text-3xl dark:text-slate-100">
                Centre de communication & suivi qualité
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                Centralisation des notifications, alertes, e-mails produit, litiges clients et incidents terrain.
                L’objectif est de réduire les oublis, accélérer les réponses et garder une trace claire de chaque action.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4 lg:min-w-[520px]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Alertes</p>
                <p class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">Priorité</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">à traiter</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Emails</p>
                <p class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">Produit</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">templates</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Support</p>
                <p class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">SLA</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">qualité</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Traçabilité</p>
                <p class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">Logs</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">audit</p>
            </div>
        </div>
    </div>
</section>
