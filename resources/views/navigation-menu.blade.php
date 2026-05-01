<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-gray-100 bg-white">
    @php
        $user = auth()->user();

        $clientLinks = [
            ['label' => 'Accueil', 'route' => 'client.dashboard', 'active' => 'client.dashboard', 'icon' => '🏠'],
            ['label' => 'Nouveau RDV', 'route' => 'client.rendezvous.create', 'active' => 'client.rendezvous.create', 'icon' => '➕'],
            ['label' => 'Mes rendez-vous', 'route' => 'client.rendezvous.index', 'active' => 'client.rendezvous.index', 'icon' => '📅'],
            ['label' => 'Historique', 'route' => 'client.historique', 'active' => 'client.historique', 'icon' => '🕘'],
            ['label' => 'Finance', 'route' => 'client.finance', 'active' => 'client.finance', 'icon' => '💳'],
            ['label' => 'Profil client', 'route' => 'client.profile', 'active' => 'client.profile', 'icon' => '👤'],
            ['label' => 'Favoris employés', 'route' => 'client.favorite-employes', 'active' => 'client.favorite-employes', 'icon' => '⭐'],
            ['label' => 'Portefeuille', 'route' => 'client.wallet', 'active' => 'client.wallet', 'icon' => '👛'],
            ['label' => 'Litiges', 'route' => 'client.claims', 'active' => 'client.claims', 'icon' => '⚠️'],
            ['label' => 'Abonnements', 'route' => 'client.subscriptions', 'active' => 'client.subscriptions', 'icon' => '🔁'],
        ];

        $employeLinks = [
            ['label' => 'Ma journée', 'route' => 'employe.dashboard', 'active' => 'employe.dashboard', 'icon' => '🏠'],
            ['label' => 'Mes missions', 'route' => 'employe.missions', 'active' => 'employe.missions*', 'icon' => '📋'],
            ['label' => 'Planning', 'route' => 'employe.planning', 'active' => 'employe.planning', 'icon' => '📅'],
            ['label' => 'Disponibilités', 'route' => 'employe.disponibilites', 'active' => 'employe.disponibilites', 'icon' => '🕒'],
            ['label' => 'Historique', 'route' => 'employe.historique', 'active' => 'employe.historique', 'icon' => '🕘'],
            ['label' => 'Incident', 'route' => 'employe.incident', 'active' => 'employe.incident', 'icon' => '⚠️'],
            ['label' => 'Équipe terrain', 'route' => 'employe.team', 'active' => 'employe.team', 'icon' => '👥'],
            ['label' => 'Coordination', 'route' => 'employe.coordination', 'active' => 'employe.coordination', 'icon' => '🧭'],
            ['label' => 'Chef d’équipe', 'route' => 'employe.teamlead.operations', 'active' => 'employe.teamlead.operations', 'icon' => '🧑‍💼'],
        ];

        $adminLinks = [
            ['label' => 'Pilotage', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => '📊'],
            ['label' => 'Missions', 'route' => 'admin.missions', 'active' => 'admin.missions*', 'icon' => '📋'],
            ['label' => 'Alertes', 'route' => 'admin.alerts', 'active' => 'admin.alerts', 'icon' => '🚨'],
            ['label' => 'Analytics', 'route' => 'admin.analytics', 'active' => 'admin.analytics', 'icon' => '📈'],
            ['label' => 'Crédits clients', 'route' => 'admin.customer.credits', 'active' => 'admin.customer.credits', 'icon' => '💰'],
            ['label' => 'Stripe prestataires', 'route' => 'admin.stripe-connect.providers', 'active' => 'admin.stripe-connect.providers', 'icon' => '💳'],
            ['label' => 'IA Dispatch', 'route' => 'admin.ai.dispatch', 'active' => 'admin.ai.dispatch', 'icon' => '🤖'],
            ['label' => 'Business', 'route' => 'admin.business.dashboard', 'active' => 'admin.business.dashboard', 'icon' => '🏢'],
            ['label' => 'Readiness', 'route' => 'admin.platform.readiness', 'active' => 'admin.platform.readiness', 'icon' => '✅'],
            ['label' => 'Factures B2B', 'route' => 'admin.b2b.monthly-invoices', 'active' => 'admin.b2b.monthly-invoices', 'icon' => '🧾'],
            ['label' => 'Approbations', 'route' => 'admin.enterprise.approvals', 'active' => 'admin.enterprise.approvals', 'icon' => '🏢'],
            ['label' => 'Sites', 'route' => 'admin.sites', 'active' => 'admin.sites', 'icon' => '📍'],
        ];

        $roleLinks = collect();

        if ($user?->isClient()) {
            $roleLinks = collect($clientLinks);
        } elseif ($user?->isEmploye()) {
            $roleLinks = collect($employeLinks);
        } elseif ($user?->isAdmin()) {
            $roleLinks = collect($adminLinks);
        }

        $roleLinks = $roleLinks
            ->filter(fn ($link) => Route::has($link['route']))
            ->values();

        $primaryLinks = $roleLinks->take(6);
        $moreLinks = $roleLinks->slice(6)->values();
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex min-w-0">
                <div class="flex shrink-0 items-center">
                    <a href="{{ auth()->check() && Route::has('dashboard') ? route('dashboard') : route('home') }}"
                       class="text-xl font-extrabold text-blue-700">
                        {{ config('app.name', 'CleanUx') }}
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

                        @if($moreLinks->isNotEmpty())
                            <div class="relative">
                                <x-dropdown align="left" width="60">
                                    <x-slot name="trigger">
                                        <button type="button"
                                                class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-gray-500 transition duration-150 ease-in-out hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700 focus:outline-none">
                                            Plus
                                            <svg class="ms-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                      clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        @foreach($moreLinks as $link)
                                            <x-dropdown-link :href="route($link['route'])">
                                                <span class="me-2">{{ $link['icon'] }}</span>
                                                {{ $link['label'] }}
                                            </x-dropdown-link>
                                        @endforeach
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
                           class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                            ➕ Réserver
                        </a>
                    @endif

                    @if(Route::has('notifications.index'))
                        <a href="{{ route('notifications.index') }}"
                           class="relative inline-flex items-center rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200">
                            🔔 Notifications

                            @if($user->unreadNotifications()->count() > 0)
                                <span class="ms-2 inline-flex min-w-[1.5rem] justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">
                                    {{ min($user->unreadNotifications()->count(), 99) }}
                                </span>
                            @endif
                        </a>
                    @endif

                    <div class="relative ms-2">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center rounded-lg border border-transparent bg-gray-100 px-3 py-2 text-sm font-medium leading-4 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-200 focus:outline-none">
                                    <div>{{ $user->name }}</div>
                                    <div class="ms-2">
                                        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20">
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="block px-4 py-2 text-xs text-gray-400">
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

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf

                                    <x-dropdown-link :href="route('logout')"
                                                     onclick="event.preventDefault(); this.closest('form').submit();">
                                        Déconnexion
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @else
                    @if(Route::has('booking.create'))
                        <a href="{{ route('booking.create') }}"
                           class="text-sm font-medium text-gray-700 hover:text-blue-700">
                            Réserver
                        </a>
                    @endif

                    @if(Route::has('login'))
                        <a href="{{ route('login') }}"
                           class="text-sm font-medium text-gray-700 hover:text-blue-700">
                            Connexion
                        </a>
                    @endif

                    @if(Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Inscription
                        </a>
                    @endif
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none">
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

    <div :class="{ 'block': open, 'hidden': !open }" class="hidden border-t border-gray-100 bg-white sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            @auth
                @foreach($roleLinks as $link)
                    <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])">
                        <span class="me-2">{{ $link['icon'] }}</span>
                        {{ $link['label'] }}
                    </x-responsive-nav-link>
                @endforeach

                @if(Route::has('notifications.index'))
                    <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.index')">
                        🔔 Notifications
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
            @endauth
        </div>

        @auth
            <div class="border-t border-gray-200 pb-1 pt-4">
                <div class="px-4">
                    <div class="text-base font-medium text-gray-800">{{ $user->name }}</div>
                    <div class="text-sm font-medium text-gray-500">{{ $user->email }}</div>
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

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')"
                                               onclick="event.preventDefault(); this.closest('form').submit();">
                            Déconnexion
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @else
            <div class="space-y-3 border-t border-gray-200 pb-4 pt-4">
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