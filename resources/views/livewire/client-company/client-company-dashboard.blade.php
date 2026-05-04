<div class="min-h-screen bg-slate-50 p-6">

    {{-- ── Header ── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Tableau de bord</p>
            <h1 class="text-2xl font-black text-slate-900">
                🏢 {{ Auth::user()->currentOrganization?->name }}
            </h1>
        </div>
        <div class="flex items-center gap-2">
            @foreach (['month' => 'Ce mois', 'week' => 'Cette semaine', 'year' => 'Cette année'] as $val => $label)
                <button wire:click="$set('period', '{{ $val }}')"
                    class="rounded-xl px-3 py-1.5 text-xs font-semibold transition border
                        {{ $period === $val
                            ? 'bg-purple-600 text-white border-purple-600'
                            : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── Approbations en attente ── --}}
    @if ($pendingApprovals->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-lg">⏳</span>
                <p class="font-bold text-amber-800">
                    {{ $pendingApprovals->count() }} demande(s) en attente de votre approbation
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($pendingApprovals->take(3) as $approval)
                    <div class="flex items-center gap-2 rounded-xl border border-amber-200 bg-white px-3 py-2">
                        <span class="text-xs text-amber-700">
                            📍 {{ $approval->organizationSite?->name ?? 'Site inconnu' }}
                        </span>
                        <span class="text-[10px] text-amber-500">
                            par {{ $approval->clientUser?->name }}
                        </span>
                        <a href="{{ route('client-company.bookings.index') }}"
                           class="rounded-lg bg-amber-600 px-2 py-0.5 text-[10px] font-bold text-white hover:bg-amber-700">
                            Voir →
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── KPIs ── --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @php
            $cards = [
                ['value' => $kpis['sites_count'],      'label' => 'Locaux',            'icon' => '🏠', 'route' => 'client-company.sites',    'color' => 'purple'],
                ['value' => $kpis['bookings_active'],   'label' => 'Missions actives',  'icon' => '🔄', 'route' => 'client-company.bookings.index', 'color' => 'blue'],
                ['value' => $kpis['bookings_period'],   'label' => 'Réservations mois', 'icon' => '📋', 'route' => 'client-company.bookings.index', 'color' => 'indigo'],
                ['value' => $kpis['pending_approval'],  'label' => 'À approuver',       'icon' => '⏳', 'route' => 'client-company.bookings.index', 'color' => 'amber'],
                ['value' => $kpis['members_count'],     'label' => 'Membres',           'icon' => '👥', 'route' => 'client-company.members',   'color' => 'teal'],
                ['value' => $kpis['spend_period'] . '€', 'label' => 'Dépenses mois',   'icon' => '💶', 'route' => 'client-company.billing',   'color' => 'green'],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ route($card['route']) }}"
               class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md hover:border-purple-200">
                <p class="text-lg mb-1">{{ $card['icon'] }}</p>
                <p class="text-xl font-black text-slate-900">{{ $card['value'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5 group-hover:text-purple-600 transition">{{ $card['label'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- ── Grille principale ── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Réservations récentes --}}
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">📋 Réservations récentes</h2>
                <div class="flex items-center gap-2">
                    <a href="{{ route('client-company.bookings.create') }}"
                       class="rounded-xl bg-purple-600 px-3 py-1 text-xs font-bold text-white hover:bg-purple-700">
                        + Nouvelle réservation
                    </a>
                    <a href="{{ route('client-company.bookings.index') }}"
                       class="text-xs text-purple-600 hover:text-purple-700">
                        Tout voir →
                    </a>
                </div>
            </div>

            <div class="space-y-2">
                @forelse ($recentBookings as $booking)
                    @php
                        $statusConfig = [
                            'pending'          => ['label' => 'En attente',  'bg' => 'bg-slate-100 text-slate-600'],
                            'pending_approval' => ['label' => 'À approuver', 'bg' => 'bg-amber-100 text-amber-700'],
                            'confirmed'        => ['label' => 'Confirmée',   'bg' => 'bg-blue-100 text-blue-700'],
                            'in_progress'      => ['label' => '🔄 En cours', 'bg' => 'bg-green-100 text-green-700'],
                            'completed'        => ['label' => '✅ Terminée', 'bg' => 'bg-emerald-100 text-emerald-700'],
                            'cancelled'        => ['label' => 'Annulée',     'bg' => 'bg-red-100 text-red-600'],
                        ];
                        $sc = $statusConfig[$booking->status] ?? ['label' => $booking->status, 'bg' => 'bg-slate-100 text-slate-600'];
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $booking->organizationSite?->name ?? 'Site inconnu' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $booking->scheduled_at ? \Carbon\Carbon::parse($booking->scheduled_at)->format('d/m/Y H:i') : '—' }}
                                @if ($booking->organizationSite?->city)
                                    · {{ $booking->organizationSite->city }}
                                @endif
                            </p>
                        </div>
                        @if ($booking->providerUser)
                            <div class="flex items-center gap-1.5">
                                <img src="{{ $booking->providerUser->profile_photo_url }}"
                                     class="h-6 w-6 rounded-full object-cover"
                                     title="{{ $booking->providerUser->name }}">
                                <span class="text-xs text-slate-500 hidden sm:block">{{ $booking->providerUser->name }}</span>
                            </div>
                        @endif
                        <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $sc['bg'] }}">
                            {{ $sc['label'] }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-10 text-center">
                        <p class="text-3xl mb-2">📋</p>
                        <p class="text-sm text-slate-400">Aucune réservation pour le moment</p>
                        <a href="{{ route('client-company.bookings.create') }}"
                           class="mt-3 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white hover:bg-purple-700">
                            Créer ma première réservation →
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Sites + navigation rapide --}}
        <div class="space-y-4">

            {{-- Sites overview --}}
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">🏠 Mes locaux</h2>
                    <a href="{{ route('client-company.sites') }}"
                       class="text-xs text-purple-600 hover:text-purple-700">
                        Gérer →
                    </a>
                </div>

                @if ($sitesOverview->isEmpty())
                    <a href="{{ route('client-company.sites') }}"
                       class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-purple-200 bg-purple-50 py-8 text-center transition hover:bg-purple-100">
                        <p class="text-2xl mb-1">🏠</p>
                        <p class="text-xs font-semibold text-purple-700">Enregistrer un local</p>
                    </a>
                @else
                    <div class="space-y-2">
                        @foreach ($sitesOverview as $site)
                            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $site->city }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($site->active_bookings_count > 0)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                            {{ $site->active_bookings_count }} actif
                                        </span>
                                    @endif
                                    <a href="{{ route('client-company.bookings.create', ['site' => $site->id]) }}"
                                       class="rounded-lg bg-purple-100 px-2 py-1 text-[10px] font-bold text-purple-700 hover:bg-purple-200">
                                        ⚡
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Navigation rapide --}}
            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">⚡ Accès rapides</h2>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([
                        ['route' => 'client-company.bookings.create', 'icon' => '+ RDV', 'label' => 'Réserver',     'color' => 'bg-purple-600 text-white'],
                        ['route' => 'client-company.sites',           'icon' => '🏠',     'label' => 'Mes locaux',   'color' => 'bg-white border border-slate-200 text-slate-700'],
                        ['route' => 'client-company.members',         'icon' => '👥',     'label' => 'Membres',      'color' => 'bg-white border border-slate-200 text-slate-700'],
                        ['route' => 'client-company.billing',         'icon' => '🧾',     'label' => 'Facturation',  'color' => 'bg-white border border-slate-200 text-slate-700'],
                    ] as $link)
                        <a href="{{ route($link['route']) }}"
                           class="flex flex-col items-center gap-1 rounded-xl p-3 text-center transition hover:shadow-md {{ $link['color'] }}">
                            <span class="text-lg">{{ $link['icon'] }}</span>
                            <span class="text-[11px] font-semibold">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
