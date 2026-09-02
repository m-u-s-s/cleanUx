{{-- LA PAGE IMBRIQUAIT UNE PAGE ROUTEE ENTIERE dans une carte, bandeau editorial compris.
     Un lien suffit — et les liens sont filtres sur la capacite, pas sur l existence de la route. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Infrastructure plateforme"
        title="Outils administrateur"
        subtitle="Exports, imports, signaux consolidés, journaux et contrôles d’état — les gestes techniques qui ne relèvent d’aucun métier.">
        <x-slot:actions>
            <span class="brio-inline-stat">{{ number_format($this->reperes['rendez_vous'], 0, ',', ' ') }} rendez-vous</span>
            <span class="brio-inline-stat">{{ number_format($this->reperes['utilisateurs'], 0, ',', ' ') }} comptes</span>
        </x-slot:actions>
    </x-page-shell>

    {{-- L ETAT DE LA PLATEFORME EN HAUT DE PAGE : ces comptes vivaient au fond d une carte
         d outils de test, la ou personne ne les cherchait. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-kpi-card title="Comptes" icon="👥" :value="number_format($this->reperes['utilisateurs'], 0, ',', ' ')" />
        <x-kpi-card title="Clients" icon="🙋" :value="number_format($this->reperes['clients'], 0, ',', ' ')" />
        <x-kpi-card title="Prestataires" icon="🧰" :value="number_format($this->reperes['prestataires'], 0, ',', ' ')" />
        <x-kpi-card title="Rendez-vous" icon="📅" :value="number_format($this->reperes['rendez_vous'], 0, ',', ' ')" />
        <x-kpi-card title="Retours" icon="⭐" :value="number_format($this->reperes['retours'], 0, ',', ' ')" />
        <x-kpi-card title="Journaux" icon="🗂️" :value="number_format($this->reperes['journaux'], 0, ',', ' ')" />
    </div>

    <x-admin.recapitulatif-acces />

    {{-- ── Données ──────────────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <h2 class="brio-section-title">Données</h2>
        <p class="brio-section-subtitle">Faire sortir un jeu de données, ou en faire entrer un sans casser le référentiel.</p>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <x-app-card title="Exportation" subtitle="Rendez-vous, comptes ou retours, en CSV ou en PDF.">
                <livewire:admin.export-tools />
            </x-app-card>

            <x-app-card title="Importation CSV" subtitle="Prépare un import de clients ou de rendez-vous avant écriture.">
                <livewire:admin.import-csv />
            </x-app-card>
        </div>
    </section>

    {{-- ── Observation ──────────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <h2 class="brio-section-title">Observation</h2>
        <p class="brio-section-subtitle">Ce que la plateforme fait en ce moment, et ce qu’elle a fait.</p>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <x-app-card title="Statistiques consolidées" subtitle="Les signaux opérationnels globaux, en une vue.">
                <livewire:admin.stats-globale />
            </x-app-card>

            <x-app-card title="Journal d’activité" subtitle="Les traces récentes, filtrables par action.">
                <livewire:admin.logs-activity />
            </x-app-card>
        </div>
    </section>

    {{-- ── Contrôle ─────────────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <h2 class="brio-section-title">Contrôle de l’environnement</h2>
        <p class="brio-section-subtitle">Les commandes à passer au terminal — elles ne s’exécutent pas depuis le navigateur.</p>

        <x-app-card title="Semis et vérifications" subtitle="Profils de semis et contrôles d’intégrité, à copier dans un terminal.">
            <livewire:admin.outils-de-test />
        </x-app-card>
    </section>

    {{-- ── Pages voisines ───────────────────────────────────────────────── --}}
    @if($this->pagesLiees !== [])
        <section class="space-y-3">
            <h2 class="brio-section-title">Pages voisines</h2>
            <p class="brio-section-subtitle">
                Des écrans à part entière, listés ici parce qu’on les cherche depuis les outils.
                Seuls ceux que vos permissions ouvrent sont affichés.
            </p>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($this->pagesLiees as $page)
                    <a href="{{ $page['url'] }}"
                       class="brio-card flex flex-col gap-1 p-4 transition hover:opacity-90">
                        <span class="font-semibold">{{ $page['titre'] }}</span>
                        <span class="text-sm opacity-70">{{ $page['resume'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
