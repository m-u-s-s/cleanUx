{{--
    LE RÉCAPITULATIF D'ACCÈS — replié par défaut, ouvrable au doigt.

    Son bouton mesurait 58 × 20 pixels : au-dessous de la cible tactile minimale, et le seul
    défaut d'accessibilité restant sur les 121 pages du harnais. Un administrateur qui ouvre
    `/admin/outils` depuis son téléphone visait un trait de vingt pixels de haut.

    Il portait aussi ses couleurs en dur — `bg-white`, `text-blue-600`, `bg-gray-100` — là où
    la charte impose les jetons. Sur la nuit, l'en-tête gris clair du tableau restait clair.

    `<details>` PLUTÔT QU'ALPINE : le pliage est natif, il fonctionne sans JavaScript, le
    clavier et les lecteurs d'écran le connaissent, et l'état ouvert/fermé n'a plus besoin
    d'être tenu à deux endroits.
--}}
<details class="brio-card !p-4">
    <summary class="brio-recap-tete">
        <h2 class="brio-section-title !text-base">📄 {{ __('ui.access_matrix.title') }}</h2>

        {{-- Les deux libellés vivent dans le même élément : la flèche du `<summary>` porte
             déjà l'état, et deux textes alternés auraient dit la même chose deux fois. --}}
        <span class="brio-chip">🔎 {{ __('ui.access_matrix.show') }}</span>
    </summary>

    <div class="brio-table-cadre mt-3">
        <table class="w-full">
            <caption class="sr-only">{{ __('ui.access_matrix.title') }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('ui.access_matrix.feature') }}</th>
                    <th scope="col" class="text-center">👑 {{ __('ui.access_matrix.admin') }}</th>
                    <th scope="col" class="text-center">👨‍🔧 {{ __('ui.access_matrix.employee') }}</th>
                    <th scope="col" class="text-center">👤 {{ __('ui.access_matrix.client') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach([
                    __('ui.access_matrix.dashboard') => [true, true, true],
                    __('ui.access_matrix.public_booking') => [false, false, true],
                    __('ui.access_matrix.see_bookings') => [false, true, true],
                    __('ui.access_matrix.validate_single') => [false, true, false],
                    __('ui.access_matrix.validate_bulk') => [false, true, false],
                    __('ui.access_matrix.leave_feedback') => [false, false, true],
                    __('ui.access_matrix.edit_feedback') => [false, false, true],
                    __('ui.access_matrix.see_feedback') => [true, true, true],
                    __('ui.access_matrix.global_notifications') => [true, true, true],
                    __('ui.access_matrix.export') => [true, false, false],
                    __('ui.access_matrix.import') => [true, false, false],
                    __('ui.access_matrix.stats') => [true, false, false],
                    __('ui.access_matrix.sessions') => [true, true, true],
                    __('ui.access_matrix.activity_logs') => [true, false, false],
                    __('ui.access_matrix.manage_users') => [true, false, false],
                    __('ui.access_matrix.edit_limits') => [true, true, false],
                ] as $label => [$admin, $employe, $client])
                    <tr>
                        <th scope="row" class="text-left font-normal">{{ $label }}</th>

                        {{-- Le symbole est DÉCORATIF : seul le texte alternatif porte la réponse.
                             Un tiret lu à voix haute ne dit pas « non ». --}}
                        @foreach([$admin, $employe, $client] as $autorise)
                            <td class="text-center">
                                <span aria-hidden="true">{{ $autorise ? '✅' : '—' }}</span>
                                <span class="sr-only">{{ $autorise ? __('Oui') : __('Non') }}</span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
