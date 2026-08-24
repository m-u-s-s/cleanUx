<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
    @php
    $user = auth()->user();

    $homeHref = auth()->check() && Route::has('dashboard')
    ? route('dashboard')
    : (Route::has('home') ? route('home') : url('/'));

    $unreadCount = auth()->check()
    ? min($user->unreadNotifications()->count(), 99)
    : 0;

    /*
     * L'APERÇU DE LA CLOCHE — cinq messages au plus.
     *
     * Une seule requête bornée s'ajoute au comptage déjà présent, et seulement s'il y a quelque
     * chose à montrer : sans notification non lue, aucune requête supplémentaire n'est faite. Le
     * panneau est un aperçu, pas la page : `notifications.index` reste la seule à tout montrer.
     */
    $apercuNotifications = $unreadCount > 0
    ? $user->unreadNotifications()->latest()->take(5)->get()
    : collect();

    /*
     * LES LIENS VIENNENT DU REGISTRE, PLUS DE CETTE VUE.
     *
     * Trois tableaux vivaient ici — 126 liens en 22 groupes — déversés dans un menu déroulant
     * « Toutes les pages » que personne ne pouvait lire. Deux autres registres du même genre
     * vivaient dans les layouts société : quatre listes à tenir à jour, et un module ajouté
     * n'apparaissait dans aucune tant qu'on n'y pensait pas.
     *
     * `config/modules.php` les remplace, et `CatalogueDesModulesTest` échoue désormais si une page
     * de tableau de bord n'y a pas sa case.
     */
    // Le rôle canonique, tranché une fois dans `Role` — plus une cascade de `is*()` par surface.
    $roleCanonique = $user?->roleCanonique();

    $contexte = match (true) {
        /*
         * L'ORDRE COMPTE, et il reste celui de `routes/authenticated.php`. Ces rôles ne s'excluent
         * pas : promouvoir un client en administrateur ne lui retire pas son profil client, donc
         * `isClient()` ET `isAdmin()` peuvent être vrais en même temps. Tant que `isClient()` était
         * testé en premier, `isAdmin()` n'était jamais atteint : le compte gardait le menu client,
         * sans le moindre lien vers l'administration.
         */
        $user?->isAdmin() => 'admin',
        $user?->isClient() => 'client',
        $user?->isEmploye() => 'employe',
        default => null,
    };

    $primaryLinks = $contexte
    ? \App\Support\Navigation\ModuleCatalogue::principaux($contexte)
    : collect();

    $modulesRoute = match ($contexte) {
        'admin' => 'admin.modules.directory',
        'client' => 'client.modules',
        'employe' => 'employe.modules',
        default => null,
    };


    // La table emoji → Heroicon vivait ici. Elle est partagée depuis `ModuleIcons` : la page
    // Modules et les deux layouts société la consomment aussi, et trois copies auraient divergé.
    $renderIcon = function (string $icon) {
        $name = \App\Support\Navigation\ModuleIcons::heroicon($icon);
        if ($name) {
            return view('components.ui.icon', ['name' => $name, 'class' => 'w-4 h-4 shrink-0'])->render();
        }
        return '<span class="text-base leading-none">' . e($icon) . '</span>';
    };
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex min-w-0">
                <div class="flex shrink-0 items-center">
                    <a href="{{ $homeHref }}" class="flex items-center gap-2 text-xl font-black tracking-tight text-blue-700 dark:text-blue-400">
                        {{-- La marque de l'espace où l'on se trouve : « Client » pour un client,
                             « Provider » pour un prestataire. La pastille « Br » qui vivait ici ne
                             distinguait rien et n'était la marque de personne. --}}
                        <x-brand.logo :size="36" />
                        <span>{{ config('app.name', 'Brio') }}</span>
                    </a>
                </div>

                <div class="hidden sm:-my-px sm:ms-8 sm:flex sm:items-center sm:gap-6">
                    @auth
                    @foreach($primaryLinks as $link)
                    <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['route']) || request()->routeIs($link['route'].'.*')">
                        <span class="me-1 inline-flex items-center">{!! $renderIcon($link['icon']) !!}</span>
                        {{--
                            `__()` ET NON LE LIBELLÉ BRUT.

                            Les libellés de `config/modules.php` sortaient tels quels : la
                            navigation restait en français quelle que soit la langue choisie,
                            pendant que le contenu de la page, lui, se traduisait. Un client
                            anglophone lisait « Historique » à côté de « New booking ».

                            Une clé sans traduction s'affiche inchangée : un francophone ne
                            voit donc aucune différence, et les langues servies cessent
                            d'être à moitié appliquées.
                        --}}
                        {{ __($link['label']) }}
                    </x-nav-link>
                    @endforeach

                    {{-- La porte vers tout le reste. Sans elle, les modules non-principaux
                         deviendraient injoignables d'un seul coup. --}}
                    @if($modulesRoute && Route::has($modulesRoute))
                    <x-nav-link :href="route($modulesRoute)" :active="request()->routeIs($modulesRoute)">
                        <span class="me-1 inline-flex items-center">{!! $renderIcon('🧩') !!}</span>
                        Modules
                    </x-nav-link>
                    @endif
                    @else
                    @if(Route::has('booking.create'))
                    <x-nav-link :href="route('booking.create')" :active="request()->routeIs('booking.create')">
                        Réserver
                    </x-nav-link>
                    @endif

                    @if(Route::has('premium.offer'))
                    <x-nav-link :href="route('premium.offer')" :active="request()->routeIs('premium.offer')">
                        Premium
                    </x-nav-link>
                    @endif
                    @endauth
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-3">
                <x-theme-toggle />
                <x-language-switcher />

                @auth
                @if($user?->isClient() && Route::has('client.rendezvous.create'))
                <a href="{{ route('client.rendezvous.create') }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">
                    ➕ Réserver
                </a>
                @endif

                {{-- « Calendrier interactif » et « Templates 1-clic » vivaient ici en liens nus,
                     sans style, hors du registre. Ils ont désormais leur case dans la page
                     Modules, catégorie Rendez-vous. --}}

                {{--
                    LA PASSERELLE VERS L'ESPACE SOCIÉTÉ RESTE DANS LA BARRE.

                    Ce n'est pas un module parmi d'autres : c'est un CHANGEMENT D'ESPACE, et
                    l'enfouir d'un cran a déjà coûté cher ici. Le layout société porte d'ailleurs
                    le lien symétrique — « Revenir à mon espace personnel ».

                    La condition est celle du registre, pas une copie : `belongsToClientCompany()`.
                    Un particulier ne doit pas voir une porte qui répondra 403.
                --}}
                {{--
                    LA PORTE DU SUPER ADMINISTRATEUR.

                    Son tableau de bord regarde la PLATEFORME — la population par rôle, les leviers
                    qui engagent tout le monde — là où la console pilote l'exploitation. Sans ce
                    lien, la page ne serait atteignable qu'en tapant son URL, et le sixième rôle
                    n'aurait de réalité que dans une énumération.
                --}}
                @if($roleCanonique === \App\Enums\Role::SUPER_ADMIN && Route::has('super-admin.dashboard'))
                <a href="{{ route('super-admin.dashboard') }}"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">
                    <x-ui.icon name="shield-check" class="h-4 w-4" />
                    <span class="hidden lg:inline">Super admin</span>
                </a>
                @endif

                @php $porteSociete = \App\Support\Navigation\ModuleCatalogue::porteVersLEspaceSociete(); @endphp
                @if($porteSociete)
                <a href="{{ route($porteSociete['route']) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span>{{ $porteSociete['icon'] }}</span>
                    <span class="hidden lg:inline">{{ __($porteSociete['label']) }}</span>
                </a>
                @endif

                @if(Route::has('notifications.index'))
                {{--
                    LA CLOCHE SEULE, ET SON APERÇU AU SURVOL.

                    Le bouton portait « 🔔 Notifications » en toutes lettres : il fallait quitter sa
                    page pour savoir ce qu'il y avait dedans. Le panneau est rendu côté serveur et
                    révélé au survol — rien à charger au moment où la souris arrive.

                    `@mouseleave` est posé sur le CONTENEUR et non sur la cloche : sur le bouton
                    seul, le panneau se fermerait dès que la souris descendrait vers lui, et
                    deviendrait impossible à lire.
                --}}
                <div class="relative" x-data="{ ouvert: false }" @mouseenter="ouvert = true" @mouseleave="ouvert = false">
                    <a href="{{ route('notifications.index') }}"
                        data-cloche-compteur="{{ $unreadCount }}"
                        aria-label="Notifications{{ $unreadCount > 0 ? ' ('.$unreadCount.' non '.($unreadCount > 1 ? 'lues' : 'lue').')' : '' }}"
                        class="relative inline-flex items-center rounded-xl bg-slate-100 p-2 text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                        <x-ui.icon name="bell" class="h-5 w-5" />

                        @if($unreadCount > 0)
                        <span class="absolute -end-1 -top-1 inline-flex min-w-[1.25rem] justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-black leading-none text-white">
                            {{ $unreadCount }}
                        </span>
                        @endif
                    </a>

                    <div x-show="ouvert"
                        x-transition.opacity.duration.150ms
                        x-cloak
                        class="absolute end-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <div class="border-b border-slate-100 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-400 dark:border-slate-700 dark:text-slate-500">
                            Notifications
                        </div>

                        <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                            @forelse($apercuNotifications as $notification)
                            @php
                            $donnees = $notification->data ?? [];
                            $message = $donnees['message'] ?? __('ui.notifications.item_fallback');
                            @endphp
                            {{--
                                CHAQUE LIGNE MÈNE À SA NOTIFICATION, pas au centre.

                                Les cinq entrées de l'aperçu pointaient toutes vers la même page :
                                on cliquait sur une notification précise et on retombait sur la
                                liste, à la retrouver soi-même. Elles mènent maintenant à sa fiche,
                                comme les cartes du centre.
                            --}}
                            <a href="{{ route('notifications.show', $notification->id) }}" class="block px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <p class="text-sm text-slate-800 dark:text-slate-100">{{ $message }}</p>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $notification->created_at?->diffForHumans() }}
                                </p>
                            </a>
                            @empty
                            <p class="px-4 py-6 text-center text-sm text-slate-400 dark:text-slate-500">
                                Aucune notification
                            </p>
                            @endforelse
                        </div>

                        @if($unreadCount > count($apercuNotifications))
                        <a href="{{ route('notifications.index') }}"
                            class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-semibold text-blue-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-blue-400 dark:hover:bg-slate-700/50">
                            Voir les {{ $unreadCount }} notifications
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                <div class="relative ms-2">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center rounded-xl border border-transparent bg-slate-100 px-3 py-2 text-sm font-semibold leading-4 text-slate-700 transition hover:bg-slate-200 focus:outline-none dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                                <span class="max-w-[150px] truncate">{{ $user->name }}</span>
                                <svg class="ms-2 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="block px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                Compte
                            </div>

                            @if($user?->isClient() && Route::has('client.profile'))
                            <x-dropdown-link :href="route('client.profile')">
                                👤 Espace client
                            </x-dropdown-link>
                            @endif

                            @if(Route::has('notifications.index'))
                            <x-dropdown-link :href="route('notifications.index')">
                                🔔 Notifications
                            </x-dropdown-link>
                            @endif

                            @if(Route::has('profile.show'))
                            <x-dropdown-link :href="route('profile.show')">
                                🔐 Sécurité du compte
                            </x-dropdown-link>
                            @endif

                            @if(Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Déconnexion
                                </x-dropdown-link>
                            </form>
                            @endif
                        </x-slot>
                    </x-dropdown>
                </div>
                @else
                @if(Route::has('booking.create'))
                <a href="{{ route('booking.create') }}"
                    class="text-sm font-semibold text-slate-700 hover:text-blue-700">
                    Réserver
                </a>
                @endif

                @if(Route::has('login'))
                <a href="{{ route('login') }}"
                    class="text-sm font-semibold text-slate-700 hover:text-blue-700 dark:text-slate-300 dark:hover:text-white">
                    Connexion
                </a>
                @endif

                @if(Route::has('register'))
                <a href="{{ route('register') }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">
                    Inscription
                </a>
                @endif
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    type="button"
                    aria-controls="menu-mobile"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-label="open ? '{{ __('Fermer le menu') }}' : '{{ __('Ouvrir le menu') }}'"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }"
                            class="inline-flex"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />

                        <path :class="{ 'hidden': !open, 'inline-flex': open }"
                            class="hidden"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div id="menu-mobile" :class="{ 'block': open, 'hidden': !open }" class="hidden border-t border-slate-100 bg-white sm:hidden dark:border-slate-700 dark:bg-slate-900">
        <div class="space-y-1 pb-3 pt-2">
            @auth
            @foreach($primaryLinks as $link)
            <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['route']) || request()->routeIs($link['route'].'.*')">
                <span class="me-2 inline-flex items-center">{!! $renderIcon($link['icon']) !!}</span>
                {{ __($link['label']) }}
            </x-responsive-nav-link>
            @endforeach

            @if($modulesRoute && Route::has($modulesRoute))
            <x-responsive-nav-link :href="route($modulesRoute)" :active="request()->routeIs($modulesRoute)">
                <span class="me-2 inline-flex items-center">{!! $renderIcon('🧩') !!}</span>
                Modules
            </x-responsive-nav-link>
            @endif

            @if(Route::has('notifications.index'))
            <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.index')">
                🔔 Notifications
                @if($unreadCount > 0)
                <span class="ms-2 rounded-full bg-red-500 px-2 py-0.5 text-xs font-black text-white">
                    {{ $unreadCount }}
                </span>
                @endif
            </x-responsive-nav-link>
            @endif
            @else
            @if(Route::has('booking.create'))
            <x-responsive-nav-link :href="route('booking.create')" :active="request()->routeIs('booking.create')">
                Réserver
            </x-responsive-nav-link>
            @endif

            @if(Route::has('premium.offer'))
            <x-responsive-nav-link :href="route('premium.offer')" :active="request()->routeIs('premium.offer')">
                Premium
            </x-responsive-nav-link>
            @endif
            <x-mobile-bottom-nav />
            <x-pwa-install-prompt />
            @endauth
        </div>

        @auth
        <div class="border-t border-slate-200 pb-1 pt-4 dark:border-slate-700">
            <div class="px-4">
                <div class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                <div class="mt-3 flex items-center gap-2">
                    <x-language-switcher />
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-3 space-y-1">
                @if($user?->isClient() && Route::has('client.profile'))
                <x-responsive-nav-link :href="route('client.profile')" :active="request()->routeIs('client.profile')">
                    👤 Espace client
                </x-responsive-nav-link>
                @endif

                @if(Route::has('profile.show'))
                <x-responsive-nav-link :href="route('profile.show')" :active="request()->routeIs('profile.show')">
                    🔐 Sécurité du compte
                </x-responsive-nav-link>
                @endif

                @if(Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Déconnexion
                    </x-responsive-nav-link>
                </form>
                @endif
            </div>
        </div>
        @else
        <div class="space-y-3 border-t border-slate-200 pb-4 pt-4 dark:border-slate-700">
            <div class="flex items-center gap-2 px-4">
                <x-language-switcher />
                <x-theme-toggle />
            </div>

            @if(Route::has('login'))
            <x-responsive-nav-link :href="route('login')" :active="request()->routeIs('login')">
                Connexion
            </x-responsive-nav-link>
            @endif

            @if(Route::has('register'))
            <x-responsive-nav-link :href="route('register')" :active="request()->routeIs('register')">
                Inscription
            </x-responsive-nav-link>
            @endif
        </div>
        @endauth
    </div>
</nav>