<section class="rounded-3xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-lg font-bold">Commandes de vérification recommandées</h2>
            <p class="mt-1 text-sm text-slate-300">
                À lancer avant commit ou avant déploiement.
            </p>
        </div>

        <span class="inline-flex w-fit rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white">
            Terminal
        </span>
    </div>

    <div class="mt-5 overflow-hidden rounded-2xl border border-white/10 bg-black/30">
        <pre class="overflow-x-auto p-4 text-xs leading-6 text-slate-200"><code>php artisan optimize:clear
php artisan route:list
php artisan test

php artisan go-live:readiness-report
php artisan production:health-check
php artisan app:audit-platform-integrity</code></pre>
    </div>
</section>