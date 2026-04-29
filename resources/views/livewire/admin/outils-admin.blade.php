<div class="space-y-8">
    <x-page-shell
        title="Outils administrateur"
        subtitle="Centre d’orchestration pour les exports, imports, statistiques, emails produit, logs et outils de test."
        eyebrow="Backoffice premium">
        <x-slot:actions>
            <span class="cu-inline-stat">Pilotage centralisé</span>
        </x-slot:actions>
    </x-page-shell>

    <x-admin.recapitulatif-acces />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-app-card title="Exportation des données" subtitle="Exporte rapidement les datasets clés de la plateforme.">
            <livewire:admin.export-tools />
        </x-app-card>

        <x-app-card title="Importation CSV" subtitle="Prépare des imports clients ou rendez-vous sans casser le référentiel.">
            <livewire:admin.import-csv />
        </x-app-card>

        <x-app-card title="Statistiques dynamiques" subtitle="Vue consolidée des signaux opérationnels globaux.">
            <livewire:admin.stats-globale />
        </x-app-card>

        <x-app-card title="Emails produit & aperçu" subtitle="Prévisualise les emails transactionnels et marketing.">
            <livewire:admin.product-emails-center />
        </x-app-card>

        @if(Route::has('admin.customer.credits'))
        <a href="{{ route('admin.customer.credits') }}"
            class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            💳 Crédits clients
        </a>
        @endif

        <x-app-card title="Logs système & notifications" subtitle="Visualise les traces et notifications clés depuis le backoffice.">
            <livewire:admin.logs-activity />
        </x-app-card>

        <x-app-card title="Fonctions de test & seeders" subtitle="Raccourcis utiles pour contrôler l’état du projet et les profils de seed.">
            <livewire:admin.outils-de-test />
        </x-app-card>
    </div>
</div>