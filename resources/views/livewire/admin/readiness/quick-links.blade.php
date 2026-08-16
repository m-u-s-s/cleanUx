<section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
    @php
        $readinessLinks = [
            [
                'label' => 'Readiness',
                'description' => 'Vérifier la préparation production.',
                'route' => 'admin.platform.readiness',
            ],
            [
                'label' => 'Audit logs',
                'description' => 'Contrôler les actions sensibles.',
                'route' => 'admin.audit.logs',
            ],
            [
                'label' => 'Alertes',
                'description' => 'Suivre les signaux bloquants.',
                'route' => 'admin.alerts',
            ],
            [
                'label' => 'Modules',
                'description' => 'Gérer les modules par rôle et plan.',
                'route' => 'admin.modules',
            ],
            [
                'label' => 'Dashboard business',
                'description' => 'Vue globale de pilotage.',
                'route' => 'admin.business.dashboard',
            ],
        ];
    @endphp

    @php
        /*
         * `Route::has()` DIT QUE LA PORTE EXISTE, PAS QU'ON A LA CLE.
         *
         * Trois ecrans d'administration portent une permission granulaire en plus du role :
         * `manage-modules`, `manage-services`, `manage-entreprises`. Un administrateur sans elles
         * voyait la carte, cliquait, et tombait sur un 403 nu. Meme defaut que le bandeau de
         * communication, meme correctif : la visibilite se decide sur le MEME test que le
         * middleware qui garde la route.
         */
        $permissionParRoute = [
            'admin.modules' => 'manage-modules',
            'admin.services' => 'manage-services',
            'admin.teams.partners' => 'manage-entreprises',
        ];

        $estAccessible = function (string $route) use ($permissionParRoute): bool {
            if (! Route::has($route)) {
                return false;
            }

            $permission = $permissionParRoute[$route] ?? null;

            return $permission === null || Gate::allows($permission);
        };
    @endphp

    @foreach ($readinessLinks as $link)
        @if ($estAccessible($link['route']))
            <a href="{{ route($link['route']) }}"
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-950">{{ $link['label'] }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $link['description'] }}</p>
                    </div>

                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 transition group-hover:bg-slate-950 group-hover:text-white">
                        →
                    </span>
                </div>
            </a>
        @endif
    @endforeach
</section>