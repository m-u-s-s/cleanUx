<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-950">Checklist avant production</h2>
            <p class="mt-1 text-sm text-slate-500">
                Les points essentiels à contrôler avant de publier CleanUx.
            </p>
        </div>

        <span class="inline-flex w-fit rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
            À vérifier avant go-live
        </span>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">1. APP_ENV</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Vérifier que la production utilise APP_ENV=production et APP_DEBUG=false.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">2. Sécurité</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Tester les accès admin, client, employé, zone-scoped admin et readonly admin.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">3. Emails</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Contrôler notifications, SMTP, templates produit et alertes importantes.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">4. Paiements</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Vérifier Stripe, abonnements premium, webhooks et facturation.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">5. Données</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Séparer les données demo, test et production avant le déploiement final.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">6. Logs</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Garder la traçabilité des actions sensibles : exports, rôles, paiements.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">7. Performance</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Nettoyer cache, routes, config et vues avant le build final.
            </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">8. Backup</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Prévoir sauvegarde DB, storage, fichiers critiques et rollback Git.
            </p>
        </div>
    </div>
</section>