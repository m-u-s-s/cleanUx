{{--
    Le cockpit du super administrateur.

    L'idiome visuel est CELUI DE LA CONSOLE D'ADMINISTRATION, délibérément : mêmes coquilles
    (`ui-page-header`, `brio-btn-*`), même largeur, même rythme vertical. Ces deux espaces
    appartiennent à la même personne un jour sur deux, et leur donner deux langages visuels
    demanderait de réapprendre à lire en changeant d'onglet.
--}}
<div class="min-h-screen bg-slate-50 dark:bg-slate-900">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-16 pt-6 sm:px-6 lg:px-8">

        <div class="ui-page-header">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="ui-page-eyebrow">Super administration</p>
                    <h1 class="ui-page-title">La plateforme Brio</h1>
                    <p class="ui-page-subtitle">
                        Qui sont les comptes, comment ils se répartissent, et les leviers qui
                        engagent tout le monde à la fois.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    {{-- Sans ce lien, le registre de règlement n'aurait aucune porte d'entrée :
                         une page qu'aucun écran ne mentionne n'existe pas pour son utilisateur. --}}
                    @if(Route::has('super-admin.reglement'))
                        <a href="{{ route('super-admin.reglement') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                            <span>Registre de règlement</span>
                        </a>
                    @endif
                    @if(Route::has('admin.dashboard'))
                        <a href="{{ route('admin.dashboard') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                            <x-ui.icon name="chart-bar" class="w-4 h-4" />
                            <span>Console d’administration</span>
                        </a>
                    @endif

                    @if(Route::has('admin.platform.readiness'))
                        <a href="{{ route('admin.platform.readiness') }}" class="brio-btn-primary inline-flex items-center gap-2">
                            <x-ui.icon name="check" class="w-4 h-4" />
                            <span>Préparation plateforme</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- La population, par rôle. C'est ce que le super administrateur vient voir en premier. --}}
        <section>
            <div class="flex items-baseline justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400">Comptes par rôle</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $total }} au total</p>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach(\App\Enums\Role::cases() as $role)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            {{ $role->label() }}
                        </p>
                        <p class="mt-1 text-3xl font-black text-slate-900 dark:text-white">
                            {{ $comptes[$role->value] }}
                        </p>

                        <div class="mt-3 flex items-center justify-between">
                            @if($role->porteDesSousRoles())
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                    {{ count($role->sousRoles()) }} sous-rôles
                                </span>
                            @else
                                <span class="text-[11px] text-slate-400">Pas de sous-rôle</span>
                            @endif

                            @if(Route::has($role->routeDuTableauDeBord()))
                                <a href="{{ route($role->routeDuTableauDeBord()) }}"
                                    class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                    Son tableau de bord
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Les onze sous-rôles de la société prestataire, avec leur rang d'autorité. --}}
        <section>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400">
                Sous-rôles — {{ \App\Enums\Role::PROVIDER_SOCIETE->label() }}
            </h2>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach(\App\Enums\Role::PROVIDER_SOCIETE->sousRoles() as $sousRole)
                    <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $sousRole->label() }}</span>
                        {{-- Le rang décide qui peut gérer qui : `canManage()` compare ces nombres. --}}
                        <span class="text-[11px] text-slate-400">rang {{ $sousRole->rank() }}</span>
                    </span>
                @endforeach
            </div>
        </section>

        {{-- Les leviers qui engagent toute la plateforme. --}}
        <section>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400">Leviers de plateforme</h2>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    ['route' => 'admin.utilisateurs.manage', 'label' => 'Utilisateurs', 'icon' => 'user'],
                    ['route' => 'admin.feature-flags.manager', 'label' => 'Feature flags', 'icon' => 'puzzle'],
                    ['route' => 'admin.modules', 'label' => 'Modules de la plateforme', 'icon' => 'puzzle'],
                    ['route' => 'admin.audit.center', 'label' => 'Audit', 'icon' => 'shield-check'],
                    ['route' => 'admin.gdpr.center', 'label' => 'RGPD', 'icon' => 'lock-closed'],
                    ['route' => 'admin.risk.center', 'label' => 'Risque', 'icon' => 'exclamation-triangle'],
                    ['route' => 'admin.translations.center', 'label' => 'Traductions', 'icon' => 'globe'],
                    ['route' => 'admin.modules.directory', 'label' => 'Répertoire des modules', 'icon' => 'grid'],
                ] as $levier)
                    @if(Route::has($levier['route']))
                        <a href="{{ route($levier['route']) }}"
                            class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-500">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                <x-ui.icon :name="$levier['icon']" class="h-5 w-5" />
                            </span>
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ $levier['label'] }}
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    </div>
</div>
