<section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
    @php
        /*
         * `Route::has()` DIT QUE LA PORTE EXISTE, PAS QU'ON A LA CLÉ.
         *
         * Ce panneau ne filtrait que sur l'existence de la route. Deux conséquences, toutes deux
         * constatées à l'écran sur /notifications avec un compte prestataire :
         *
         *   - « Alertes admin » et « Emails produit » s'affichaient pour tout le monde alors que
         *     `admin.alerts` / `admin.emails` portent `CheckRole:admin`. Un clic menait à une page
         *     403 nue, sans navigation ni retour.
         *   - « Notifications » et « Litiges client » pointaient sur `notifications` et
         *     `client.litiges` — deux noms qui n'existent pas (`notifications.index` et
         *     `client.claims`). `Route::has()` les avalait en silence : le tableau déclarait cinq
         *     destinations et n'en rendait que trois, dont deux interdites.
         *
         * La visibilité se décide donc sur le MÊME test que le middleware qui garde la route :
         * `matchesRole()`, celui qu'appelle `CheckRole`. Deux tests différents pour une seule
         * question, c'est le retour assuré de l'écart.
         */
        $utilisateur = auth()->user();

        $communicationLinks = [
            [
                'label' => 'Notifications',
                'description' => 'Voir les messages système et actions récentes.',
                'route' => 'notifications.index',
                'autorise' => true,
            ],
            [
                'label' => 'Alertes admin',
                'description' => 'Suivre les problèmes à traiter rapidement.',
                'route' => 'admin.alerts',
                'autorise' => (bool) $utilisateur?->matchesRole('admin'),
            ],
            [
                'label' => 'Emails produit',
                'description' => 'Prévisualiser et piloter les communications.',
                'route' => 'admin.emails',
                'autorise' => (bool) $utilisateur?->matchesRole('admin'),
            ],
            [
                'label' => 'Litiges client',
                'description' => 'Suivre les demandes et réclamations clients.',
                'route' => 'client.claims',
                'autorise' => (bool) $utilisateur?->matchesRole('client'),
            ],
            [
                'label' => 'Incident terrain',
                'description' => 'Remonter un problème depuis le terrain.',
                'route' => 'employe.incident',
                'autorise' => (bool) $utilisateur?->matchesRole('employe'),
            ],
        ];
    @endphp

    @foreach ($communicationLinks as $link)
        @if ($link['autorise'] && Route::has($link['route']))
            <a href="{{ route($link['route']) }}"
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-100">{{ $link['label'] }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $link['description'] }}</p>
                    </div>

                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 transition group-hover:bg-slate-950 group-hover:text-white dark:bg-slate-700 dark:text-slate-300 dark:group-hover:bg-slate-100 dark:group-hover:text-slate-900">
                        →
                    </span>
                </div>
            </a>
        @endif
    @endforeach
</section>
