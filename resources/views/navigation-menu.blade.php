<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="text-xl font-extrabold text-blue-700">
                        {{ config('app.name', 'App') }}
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @auth
                        @php($user = auth()->user())

                        @if($user->isClient())
                            <x-nav-link :href="route('client.dashboard')" :active="request()->routeIs('client.dashboard')">Accueil</x-nav-link>
                            <x-nav-link :href="route('client.rendezvous.create')" :active="request()->routeIs('client.rendezvous.create')">Nouveau rendez-vous</x-nav-link>
                            <x-nav-link :href="route('client.rendezvous.index')" :active="request()->routeIs('client.rendezvous.index')">Mes rendez-vous</x-nav-link>
                            <x-nav-link :href="route('client.historique')" :active="request()->routeIs('client.historique')">Historique</x-nav-link>
                            <x-nav-link :href="route('client.favorite-employes')" :active="request()->routeIs('client.favorite-employes')">Favoris</x-nav-link>
                            <x-nav-link :href="route('client.profile')" :active="request()->routeIs('client.profile')">Espace client</x-nav-link>
                        @elseif($user->isEmploye())
                            <x-nav-link :href="route('employe.dashboard')" :active="request()->routeIs('employe.dashboard')">Ma journée</x-nav-link>
                            <x-nav-link :href="route('employe.planning')" :active="request()->routeIs('employe.planning')">Planning</x-nav-link>
                            <x-nav-link :href="route('employe.missions')" :active="request()->routeIs('employe.missions')">Mes missions</x-nav-link>
                            <x-nav-link :href="route('employe.disponibilites')" :active="request()->routeIs('employe.disponibilites')">Disponibilités</x-nav-link>
                            <x-nav-link :href="route('employe.historique')" :active="request()->routeIs('employe.historique')">Historique</x-nav-link>
                            <x-nav-link :href="route('employe.team')" :active="request()->routeIs('employe.team')">Mon équipe</x-nav-link>
                        @elseif($user->isAdmin())
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Pilotage</x-nav-link>
                            <x-nav-link :href="route('admin.planning')" :active="request()->routeIs('admin.planning')">Planning</x-nav-link>
                            <x-nav-link :href="route('admin.missions')" :active="request()->routeIs('admin.missions')">Missions</x-nav-link>
                            <x-nav-link :href="route('admin.utilisateurs')" :active="request()->routeIs('admin.utilisateurs')">Utilisateurs</x-nav-link>
                            <x-nav-link :href="route('admin.feedbacks')" :active="request()->routeIs('admin.feedbacks')">Feedbacks</x-nav-link>

                            @can('manage-services')
                                <x-nav-link :href="route('admin.services')" :active="request()->routeIs('admin.services')">Services</x-nav-link>
                                <x-nav-link :href="route('admin.zones')" :active="request()->routeIs('admin.zones')">Zones</x-nav-link>
                            @endcan

                            @can('manage-entreprises')
                                <x-nav-link :href="route('admin.entreprises')" :active="request()->routeIs('admin.entreprises')">Entreprises</x-nav-link>
                                <x-nav-link :href="route('admin.teams.partners')" :active="request()->routeIs('admin.teams.partners')">Équipes & partenaires</x-nav-link>
                            @endcan

                            @can('manage-finance')
                                <x-nav-link :href="route('admin.finance')" :active="request()->routeIs('admin.finance')">Finance</x-nav-link>
                            @endcan

                            @can('manage-analytics')
                                <x-nav-link :href="route('admin.analytics')" :active="request()->routeIs('admin.analytics')">Analytics</x-nav-link>
                            @endcan

                            @can('manage-quality')
                                <x-nav-link :href="route('admin.quality')" :active="request()->routeIs('admin.quality')">Qualité</x-nav-link>
                            @endcan

                            @can('manage-calendar')
                                <x-nav-link :href="route('admin.calendar')" :active="request()->routeIs('admin.calendar')">Calendrier</x-nav-link>
                            @endcan

                            @can('manage-premium')
                                <x-nav-link :href="route('admin.premium.clients')" :active="request()->routeIs('admin.premium.clients')">Premium</x-nav-link>
                            @endcan

                            @can('manage-audit-logs')
                                <x-nav-link :href="route('admin.audit.logs')" :active="request()->routeIs('admin.audit.logs')">Audit</x-nav-link>
                            @endcan

                            <x-nav-link :href="route('admin.outils')" :active="request()->routeIs('admin.outils')">Outils</x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <x-language-switcher />
                @auth
                    @php($user = auth()->user())

                    @if($user->isClient())
                        <a href="{{ route('client.rendezvous.create') }}"
                           class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">
                            {{ __('app.reserve') }}
                        </a>
                    @endif

                    <a href="{{ route('notifications.index') }}"
                       class="relative inline-flex items-center px-3 py-2 rounded-lg bg-gray-100 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
                        {{ __('ui.navigation.notifications') }}
                        @if($user->unreadNotifications()->count() > 0)
                            <span class="ms-2 inline-flex min-w-[1.5rem] justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">
                                {{ min($user->unreadNotifications()->count(), 99) }}
                            </span>
                        @endif
                    </a>

                    <div class="relative ms-3">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none transition ease-in-out duration-150">
                                    <div>{{ $user->name }}</div>
                                    <div class="ms-2">
                                        <svg class="fill-current h-4 w-4" viewBox="0 0 20 20">
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="block px-4 py-2 text-xs text-gray-400">
                                    {{ __('app.account') }}
                                </div>

                                @if($user->isClient())
                                    <x-dropdown-link :href="route('client.profile')">{{ __('app.client_space') }}</x-dropdown-link>
                                @endif

                                <x-dropdown-link :href="route('notifications.index')">{{ __('ui.navigation.notifications') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('profile.show')">{{ __('app.account_security') }}</x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                                     onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('app.logout') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @else
                    <a href="{{ route('booking.create') }}" class="text-sm font-medium text-gray-700 hover:text-blue-700">Réserver</a>
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-blue-700">{{ __('app.login') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">{{ __('app.register') }}</a>
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-100 bg-white">
        <div class="pt-2 pb-3 space-y-1">
            @auth
                @php($user = auth()->user())

                @if($user->isClient())
                    <x-responsive-nav-link :href="route('client.dashboard')" :active="request()->routeIs('client.dashboard')">{{ __('app.nav.home') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('client.rendezvous.create')" :active="request()->routeIs('client.rendezvous.create')">{{ __('app.nav.new_booking') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('client.rendezvous.index')" :active="request()->routeIs('client.rendezvous.index')">{{ __('app.nav.my_bookings') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('client.historique')" :active="request()->routeIs('client.historique')">{{ __('app.nav.history') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('client.favorite-employes')" :active="request()->routeIs('client.favorite-employes')">{{ __('app.nav.favorites') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('client.profile')" :active="request()->routeIs('client.profile')">{{ __('app.client_space') }}</x-responsive-nav-link>
                @elseif($user->isEmploye())
                    <x-responsive-nav-link :href="route('employe.dashboard')" :active="request()->routeIs('employe.dashboard')">{{ __('app.nav.my_day') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employe.planning')" :active="request()->routeIs('employe.planning')">{{ __('app.nav.planning') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employe.missions')" :active="request()->routeIs('employe.missions')">{{ __('app.nav.missions') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employe.disponibilites')" :active="request()->routeIs('employe.disponibilites')">Disponibilités</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employe.historique')" :active="request()->routeIs('employe.historique')">{{ __('app.nav.history') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employe.team')" :active="request()->routeIs('employe.team')">Mon équipe</x-responsive-nav-link>
                @elseif($user->isAdmin())
                    <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">{{ __('app.nav.pilotage') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.planning')" :active="request()->routeIs('admin.planning')">{{ __('app.nav.planning') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.missions')" :active="request()->routeIs('admin.missions')">{{ __('app.nav.missions') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.utilisateurs')" :active="request()->routeIs('admin.utilisateurs')">{{ __('ui.navigation.user_management') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.feedbacks')" :active="request()->routeIs('admin.feedbacks')">{{ __('app.nav.feedbacks') }}</x-responsive-nav-link>

                    @can('manage-services')
                        <x-responsive-nav-link :href="route('admin.services')" :active="request()->routeIs('admin.services')">{{ __('app.nav.services') }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.zones')" :active="request()->routeIs('admin.zones')">Zones</x-responsive-nav-link>
                    @endcan
                    @can('manage-entreprises')
                        <x-responsive-nav-link :href="route('admin.entreprises')" :active="request()->routeIs('admin.entreprises')">{{ __('app.nav.companies') }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.teams.partners')" :active="request()->routeIs('admin.teams.partners')">Équipes & partenaires</x-responsive-nav-link>
                    @endcan
                    @can('manage-finance')
                        <x-responsive-nav-link :href="route('admin.finance')" :active="request()->routeIs('admin.finance')">{{ __('app.nav.finance') }}</x-responsive-nav-link>
                    @endcan
                    @can('manage-analytics')
                        <x-responsive-nav-link :href="route('admin.analytics')" :active="request()->routeIs('admin.analytics')">{{ __('app.nav.analytics') }}</x-responsive-nav-link>
                    @endcan
                    @can('manage-quality')
                        <x-responsive-nav-link :href="route('admin.quality')" :active="request()->routeIs('admin.quality')">{{ __('app.nav.quality') }}</x-responsive-nav-link>
                    @endcan
                    @can('manage-calendar')
                        <x-responsive-nav-link :href="route('admin.calendar')" :active="request()->routeIs('admin.calendar')">{{ __('app.nav.calendar') }}</x-responsive-nav-link>
                    @endcan
                    @can('manage-premium')
                        <x-responsive-nav-link :href="route('admin.premium.clients')" :active="request()->routeIs('admin.premium.clients')">{{ __('app.nav.premium') }}</x-responsive-nav-link>
                    @endcan
                    @can('manage-audit-logs')
                        <x-responsive-nav-link :href="route('admin.audit.logs')" :active="request()->routeIs('admin.audit.logs')">{{ __('app.nav.audit') }}</x-responsive-nav-link>
                    @endcan
                    <x-responsive-nav-link :href="route('admin.outils')" :active="request()->routeIs('admin.outils')">{{ __('app.nav.tools') }}</x-responsive-nav-link>
                @endif
            @endauth
        </div>

        @auth
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                    <div class="mt-3"><x-language-switcher /></div>
                </div>

                <div class="mt-3 space-y-1">
                    @if(auth()->user()->isClient())
                        <x-responsive-nav-link :href="route('client.profile')" :active="request()->routeIs('client.profile')">{{ __('app.client_space') }}</x-responsive-nav-link>
                    @endif

                    <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.index')">{{ __('ui.navigation.notifications') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('profile.show')" :active="request()->routeIs('profile.show')">{{ __('app.account_security') }}</x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                               onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('app.logout') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @else
            <div class="pt-4 pb-4 border-t border-gray-200 space-y-3">
                <div class="px-4"><x-language-switcher /></div>
                <x-responsive-nav-link :href="route('login')" :active="request()->routeIs('login')">{{ __('app.login') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('register')" :active="request()->routeIs('register')">{{ __('app.register') }}</x-responsive-nav-link>
            </div>
        @endauth
    </div>
</nav>
