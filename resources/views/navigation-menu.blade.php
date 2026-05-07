<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 shadow-sm backdrop-blur">
    @php
    $user = auth()->user();

    $homeHref = auth()->check() && Route::has('dashboard')
    ? route('dashboard')
    : (Route::has('home') ? route('home') : url('/'));

    $unreadCount = auth()->check()
    ? min($user->unreadNotifications()->count(), 99)
    : 0;

    $filterLinks = function (array $groups) {
    return collect($groups)
    ->map(function ($links, $group) {
    return [
    'group' => $group,
    'links' => collect($links)
    ->filter(fn ($link) => Route::has($link['route']))
    ->values(),
    ];
    })
    ->filter(fn ($group) => $group['links']->isNotEmpty())
    ->values();
    };

    $clientGroups = [
    'Essentiel' => [
    ['label' => 'Accueil', 'route' => 'client.dashboard', 'active' => 'client.dashboard', 'icon' => '🏠'],
    ['label' => 'Nouveau RDV', 'route' => 'client.rendezvous.create', 'active' => 'client.rendezvous.create', 'icon' => '➕'],
    ['label' => 'Mes rendez-vous', 'route' => 'client.rendezvous.index', 'active' => 'client.rendezvous.*', 'icon' => '📅'],
    ['label' => 'Historique', 'route' => 'client.historique', 'active' => 'client.historique', 'icon' => '🕘'],
    ],
    'Compte client' => [
    ['label' => 'Finance', 'route' => 'client.finance', 'active' => 'client.finance*', 'icon' => '💳'],
    ['label' => 'Portefeuille', 'route' => 'client.wallet', 'active' => 'client.wallet', 'icon' => '👛'],
    ['label' => 'Favoris employés', 'route' => 'client.favorite-employes', 'active' => 'client.favorite-employes', 'icon' => '⭐'],
    ['label' => 'Litiges', 'route' => 'client.claims', 'active' => 'client.claims*', 'icon' => '⚠️'],
    ['label' => 'Abonnements', 'route' => 'client.subscriptions', 'active' => 'client.subscriptions*', 'icon' => '🔁'],
    ['label' => 'Profil client', 'route' => 'client.profile', 'active' => 'client.profile', 'icon' => '👤'],
    ],
    ];

    $employeGroups = [
    'Terrain' => [
    ['label' => 'Ma journée', 'route' => 'employe.dashboard', 'active' => 'employe.dashboard', 'icon' => '🏠'],
    ['label' => 'Mes missions', 'route' => 'employe.missions', 'active' => 'employe.missions*', 'icon' => '📋'],
    ['label' => 'Planning', 'route' => 'employe.planning', 'active' => 'employe.planning', 'icon' => '📅'],
    ['label' => 'Disponibilités', 'route' => 'employe.disponibilites', 'active' => 'employe.disponibilites', 'icon' => '🕒'],
    ['label' => 'Historique', 'route' => 'employe.historique', 'active' => 'employe.historique', 'icon' => '🕘'],
    ],
    'Qualité & équipe' => [
    ['label' => 'Incident', 'route' => 'employe.incident', 'active' => 'employe.incident', 'icon' => '⚠️'],
    ['label' => 'Équipe terrain', 'route' => 'employe.team', 'active' => 'employe.team', 'icon' => '👥'],
    ['label' => 'Coordination', 'route' => 'employe.coordination', 'active' => 'employe.coordination', 'icon' => '🧭'],
    ['label' => 'Chef d’équipe', 'route' => 'employe.teamlead.operations', 'active' => 'employe.teamlead.operations', 'icon' => '🧑‍💼'],
    ],
    ];

    $adminGroups = [
    'Pilotage' => [
    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => '📊'],
    ['label' => 'Planning', 'route' => 'admin.planning', 'active' => 'admin.planning*', 'icon' => '📅'],
    ['label' => 'Missions', 'route' => 'admin.missions', 'active' => 'admin.missions*', 'icon' => '📋'],
    ['label' => 'Alertes', 'route' => 'admin.alerts', 'active' => 'admin.alerts', 'icon' => '🚨'],
    ['label' => 'Analytics', 'route' => 'admin.analytics', 'active' => 'admin.analytics', 'icon' => '📈'],
    ['label' => 'Finance', 'route' => 'admin.finance', 'active' => 'admin.finance*', 'icon' => '💶'],
    ],
    'Opérations' => [
    ['label' => 'Équipes & partenaires', 'route' => 'admin.teams.partners', 'active' => 'admin.teams.partners', 'icon' => '👥'],
    ['label' => 'Orchestration', 'route' => 'admin.orchestration', 'active' => 'admin.orchestration', 'icon' => '🧭'],
    ['label' => 'Automation', 'route' => 'admin.automation', 'active' => 'admin.automation', 'icon' => '⚙️'],
    ['label' => 'B2B opérations', 'route' => 'admin.b2b.operations', 'active' => 'admin.b2b.operations', 'icon' => '🏢'],
    ['label' => 'International', 'route' => 'admin.international', 'active' => 'admin.international', 'icon' => '🌍'],
    ['label' => 'Pays', 'route' => 'admin.countries', 'active' => 'admin.countries', 'icon' => '🗺️'],
    ['label' => 'Sites', 'route' => 'admin.sites', 'active' => 'admin.sites', 'icon' => '📍'],
    ],
    'Gestion' => [
    ['label' => 'Utilisateurs', 'route' => 'admin.utilisateurs.manage', 'active' => 'admin.utilisateurs*', 'icon' => '👤'],
    ['label' => 'Services', 'route' => 'admin.services', 'active' => 'admin.services', 'icon' => '🧽'],
    ['label' => 'Modules', 'route' => 'admin.modules', 'active' => 'admin.modules', 'icon' => '🧩'],
    ['label' => 'Feedbacks', 'route' => 'admin.feedbacks', 'active' => 'admin.feedbacks*', 'icon' => '💬'],
    ['label' => 'Outils admin', 'route' => 'admin.outils', 'active' => 'admin.outils', 'icon' => '🛠️'],
    ['label' => 'Clients premium', 'route' => 'admin.premium.clients', 'active' => 'admin.premium.clients', 'icon' => '⭐'],
    ['label' => 'Crédits clients', 'route' => 'admin.customer.credits', 'active' => 'admin.customer.credits', 'icon' => '💰'],
    ],
    'Business avancé' => [
    ['label' => 'IA Dispatch', 'route' => 'admin.ai.dispatch', 'active' => 'admin.ai.dispatch', 'icon' => '🤖'],
    ['label' => 'Business', 'route' => 'admin.business.dashboard', 'active' => 'admin.business.dashboard', 'icon' => '🏢'],
    ['label' => 'Readiness', 'route' => 'admin.platform.readiness', 'active' => 'admin.platform.readiness', 'icon' => '✅'],
    ['label' => 'Factures B2B', 'route' => 'admin.b2b.monthly-invoices', 'active' => 'admin.b2b.monthly-invoices', 'icon' => '🧾'],
    ['label' => 'Approbations', 'route' => 'admin.enterprise.approvals', 'active' => 'admin.enterprise.approvals', 'icon' => '📑'],
    ['label' => 'Stripe prestataires', 'route' => 'admin.stripe-connect.providers', 'active' => 'admin.stripe-connect.providers', 'icon' => '💳'],
    ['label' => 'Emails produit', 'route' => 'admin.emails', 'active' => 'admin.emails', 'icon' => '✉️'],
    ],
    ];

    $groups = collect();

    if ($user?->isClient()) {
    $groups = $filterLinks($clientGroups);
    } elseif ($user?->isEmploye()) {
    $groups = $filterLinks($employeGroups);
    } elseif ($user?->isAdmin()) {
    $groups = $filterLinks($adminGroups);
    }

    $roleLinks = $groups
    ->flatMap(fn ($group) => $group['links'])
    ->values();

    $primaryLinks = $roleLinks->take(5);
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex min-w-0">
                <div class="flex shrink-0 items-center">
                    <a href="{{ $homeHref }}" class="flex items-center gap-2 text-xl font-black tracking-tight text-blue-700">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-blue-600 text-sm font-black text-white shadow-sm">
                            CU
                        </span>
                        <span>{{ config('app.name', 'CleanUx') }}</span>
                    </a>
                </div>

                <div class="hidden sm:-my-px sm:ms-8 sm:flex sm:items-center sm:gap-6">
                    @auth
                    @foreach($primaryLinks as $link)
                    <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])">
                        <span class="me-1">{{ $link['icon'] }}</span>
                        {{ $link['label'] }}
                    </x-nav-link>
                    @endforeach

                    @if($groups->isNotEmpty())
                    <div class="relative">
                        <x-dropdown align="left" width="60">
                            <x-slot name="trigger">
                                <button type="button"
                                    class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-semibold leading-5 text-slate-500 transition hover:border-blue-300 hover:text-blue-700 focus:border-blue-400 focus:text-blue-700 focus:outline-none">
                                    Toutes les pages
                                    <svg class="ms-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="max-h-[70vh] overflow-y-auto py-2">
                                    @foreach($groups as $group)
                                    <div class="px-4 pb-1 pt-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                                        {{ $group['group'] }}
                                    </div>

                                    @foreach($group['links'] as $link)
                                    <x-dropdown-link :href="route($link['route'])">
                                        <span class="me-2">{{ $link['icon'] }}</span>
                                        {{ $link['label'] }}
                                    </x-dropdown-link>
                                    @endforeach
                                    @endforeach
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </div>
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
                <x-language-switcher />

                @auth
                @if($user?->isClient() && Route::has('client.rendezvous.create'))
                <a href="{{ route('client.rendezvous.create') }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
                    ➕ Réserver
                </a>
                @endif

                <a href="{{ route('client.calendar.interactive') }}">📅 Calendrier interactif</a>
                <a href="{{ route('client.recurring.templates') }}">⭐ Templates 1-clic</a>
                @if(Route::has('notifications.index'))
                <a href="{{ route('notifications.index') }}"
                    class="relative inline-flex items-center rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                    🔔 Notifications

                    @if($unreadCount > 0)
                    <span class="ms-2 inline-flex min-w-[1.5rem] justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-black text-white">
                        {{ $unreadCount }}
                    </span>
                    @endif
                </a>
                @endif

                <div class="relative ms-2">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center rounded-xl border border-transparent bg-slate-100 px-3 py-2 text-sm font-semibold leading-4 text-slate-700 transition hover:bg-slate-200 focus:outline-none">
                                <span class="max-w-[150px] truncate">{{ $user->name }}</span>
                                <svg class="ms-2 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="block px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-400">
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
                    class="text-sm font-semibold text-slate-700 hover:text-blue-700">
                    Connexion
                </a>
                @endif

                @if(Route::has('register'))
                <a href="{{ route('register') }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
                    Inscription
                </a>
                @endif
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none">
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

    <div :class="{ 'block': open, 'hidden': !open }" class="hidden border-t border-slate-100 bg-white sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            @auth
            @foreach($groups as $group)
            <div class="px-4 pb-1 pt-4 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                {{ $group['group'] }}
            </div>

            @foreach($group['links'] as $link)
            <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])">
                <span class="me-2">{{ $link['icon'] }}</span>
                {{ $link['label'] }}
            </x-responsive-nav-link>
            @endforeach
            @endforeach

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
        <div class="border-t border-slate-200 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-bold text-slate-800">{{ $user->name }}</div>
                <div class="text-sm font-medium text-slate-500">{{ $user->email }}</div>
                <div class="mt-3">
                    <x-language-switcher />
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
        <div class="space-y-3 border-t border-slate-200 pb-4 pt-4">
            <div class="px-4">
                <x-language-switcher />
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