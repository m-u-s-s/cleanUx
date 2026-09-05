{{-- `dessinerRepartition` vit dans ce paquet. Sans ce push, l'anneau ne se dessinait pas. --}}
@push('scripts')
    @vite(['resources/js/apexcharts.js'])
@endpush

<div class="space-y-6">

    {{-- La page recopiait le systeme en Tailwind brut : en-tete, cases et etats vides maison.
         Elle emploie desormais les memes composants que les autres tableaux de bord. --}}
    <x-page-shell
        eyebrow="Tableau de bord"
        :title="Auth::user()->currentOrganization?->name ?? __('Ma société')"
        subtitle="Réservations, locaux, membres et dépenses de votre organisation.">

        <x-slot name="actions">
            @foreach (['month' => 'Ce mois', 'week' => 'Cette semaine', 'year' => 'Cette année'] as $val => $label)
                <button type="button" wire:click="$set('period', '{{ $val }}')"
                    class="{{ $period === $val ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                    {{ $label }}
                </button>
            @endforeach
        </x-slot>

        @if ($pendingApprovals->isNotEmpty())
            <div class="brio-alerte brio-alerte-warning !mb-0 flex-wrap">
                <span class="text-lg leading-none">⏳</span>
                <span class="font-semibold">
                    {{ $pendingApprovals->count() }} demande(s) en attente de votre approbation
                </span>

                <div class="flex w-full flex-wrap gap-2 ps-7">
                    @foreach ($pendingApprovals->take(3) as $approval)
                        <a href="{{ route('client-company.bookings.index') }}" class="brio-chip">
                            <span>📍 {{ $approval->organizationSite?->name ?? 'Site inconnu' }}</span>
                            <span class="opacity-70">par {{ $approval->clientUser?->name }}</span>
                            <span aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-page-shell>

    {{-- ── KPIs ── --}}
    @php
        $cards = [
            ['value' => $kpis['sites_count'],      'label' => 'Locaux',            'icon' => '🏠', 'route' => 'client-company.sites',           'tone' => 'blue'],
            ['value' => $kpis['bookings_active'],  'label' => 'Missions actives',  'icon' => '🔄', 'route' => 'client-company.bookings.index',  'tone' => 'blue'],
            ['value' => $kpis['bookings_period'],  'label' => 'Réservations mois', 'icon' => '📋', 'route' => 'client-company.bookings.index',  'tone' => 'slate'],
            ['value' => $kpis['pending_approval'], 'label' => 'À approuver',       'icon' => '⏳', 'route' => 'client-company.bookings.index',  'tone' => 'amber'],
            ['value' => $kpis['members_count'],    'label' => 'Membres',           'icon' => '👥', 'route' => 'client-company.members',         'tone' => 'slate'],
            ['value' => app(\App\Services\Localization\Money::class)->format($kpis['spend_period'], $devise), 'label' => 'Dépenses mois', 'icon' => '💶', 'route' => 'client-company.billing', 'tone' => 'emerald'],
        ];
    @endphp

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($cards as $card)
            {{-- Chaque case reste un lien : c'etait sa seule fonction en plus d'afficher. --}}
            <a href="{{ route($card['route']) }}" class="block">
                <x-ui.stat
                    :title="$card['label']"
                    :value="$card['value']"
                    :icon="$card['icon']"
                    :tone="$card['tone']" />
            </a>
        @endforeach
    </div>

    {{-- ── Grille principale ── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Réservations récentes --}}
        <x-app-card class="lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="brio-section-title">📋 Réservations récentes</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('client-company.bookings.create') }}" class="brio-btn-primary !px-3 !py-1.5 !text-xs">
                        + Nouvelle réservation
                    </a>
                    <a href="{{ route('client-company.bookings.index') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800">
                        Tout voir →
                    </a>
                </div>
            </div>

            <div class="space-y-2">
                @forelse ($recentBookings as $booking)
                    @php
                        $statusConfig = [
                            'pending'          => ['label' => 'En attente',  'tone' => 'neutral'],
                            'pending_approval' => ['label' => 'À approuver', 'tone' => 'warning'],
                            'confirmed'        => ['label' => 'Confirmée',   'tone' => 'info'],
                            'in_progress'      => ['label' => '🔄 En cours', 'tone' => 'success'],
                            'completed'        => ['label' => '✅ Terminée', 'tone' => 'success'],
                            'cancelled'        => ['label' => 'Annulée',     'tone' => 'danger'],
                        ];
                        $sc = $statusConfig[$booking->status] ?? ['label' => $booking->status, 'tone' => 'neutral'];
                    @endphp
                    <div class="brio-list-item flex items-center gap-3 !p-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">
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
                                <img alt="{{ $booking->providerUser->name }}" src="{{ $booking->providerUser->profile_photo_url }}"
                                     class="h-6 w-6 rounded-full object-cover"
                                     title="{{ $booking->providerUser->name }}">
                                <span class="hidden text-xs text-slate-500 sm:block">{{ $booking->providerUser->name }}</span>
                            </div>
                        @endif
                        <x-ui.badge :tone="$sc['tone']" :label="$sc['label']" class="flex-shrink-0" />
                    </div>
                @empty
                    <x-empty-state
                        icon="📋"
                        title="Aucune réservation pour le moment"
                        message="Vos réservations de société apparaîtront ici dès la première demande.">
                        <a href="{{ route('client-company.bookings.create') }}" class="brio-btn-primary !text-xs">
                            Créer ma première réservation →
                        </a>
                    </x-empty-state>
                @endforelse
            </div>
        </x-app-card>

        {{-- Locaux, répartition et accès rapides --}}
        <div class="space-y-4">

            <x-app-card>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="brio-section-title">🏠 Mes locaux</h3>
                    <a href="{{ route('client-company.sites') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800">
                        Gérer →
                    </a>
                </div>

                @if ($sitesOverview->isEmpty())
                    <x-empty-state
                        icon="🏠"
                        title="Aucun local enregistré"
                        message="Enregistrez un local pour y planifier vos interventions.">
                        <a href="{{ route('client-company.sites') }}" class="brio-btn-primary !text-xs">
                            Enregistrer un local →
                        </a>
                    </x-empty-state>
                @else
                    <div class="space-y-2">
                        @foreach ($sitesOverview as $site)
                            <div class="brio-list-item flex items-center justify-between gap-3 !p-2.5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $site->city }}</p>
                                </div>
                                <div class="flex flex-shrink-0 items-center gap-2">
                                    @if ($site->active_bookings_count > 0)
                                        <x-ui.badge tone="success" :label="$site->active_bookings_count . ' actif'" />
                                    @endif
                                    <a href="{{ route('client-company.bookings.create', ['site' => $site->id]) }}"
                                       class="brio-chip"
                                       title="{{ __('Réserver pour ce local') }}">⚡</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-app-card>

            {{-- Un anneau, pas des barres CSS : comparer deux metiers demandait de mesurer
                 deux traits a l'oeil. Les donnees passent par des attributs `data-*`. --}}
            @if (! empty($bookingsByTrade))
                <section class="brio-graphique" aria-labelledby="titre-metiers">
                    <div class="brio-graphique-tete">
                        <h2 id="titre-metiers" class="brio-graphique-titre">{{ __('Réservations par métier') }}</h2>
                        <p class="brio-graphique-note">{{ $kpis['bookings_period'] }} {{ __('sur la periode') }}</p>
                    </div>

                    <div class="brio-graphique-corps" wire:ignore x-data x-init="dessinerRepartition($el)">
                        <div data-graphique
                             data-valeurs="{{ json_encode(array_column($bookingsByTrade, 'count')) }}"
                             data-libelles="{{ json_encode(array_column($bookingsByTrade, 'trade')) }}"></div>
                    </div>

                    {{-- Le tableau reste sous l'anneau : un graphique ne se lit pas au lecteur
                         d'ecran, et un chiffre exact ne se releve pas sur un angle. --}}
                    <div class="brio-table-cadre mt-4">
                    <table class="w-full">
                        <caption class="sr-only">{{ __('Réservations par métier, en chiffres') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Metier') }}</th>
                                <th scope="col" class="text-right">{{ __('Réservations') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookingsByTrade as $row)
                                <tr>
                                    <td>{{ $row['trade'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </section>
            @endif

            <x-app-card title="⚡ Accès rapides">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([
                        ['route' => 'client-company.bookings.create', 'icon' => '+ RDV', 'label' => 'Réserver',    'primaire' => true],
                        ['route' => 'client-company.sites',           'icon' => '🏠',     'label' => 'Mes locaux',  'primaire' => false],
                        ['route' => 'client-company.members',         'icon' => '👥',     'label' => 'Membres',     'primaire' => false],
                        ['route' => 'client-company.billing',         'icon' => '🧾',     'label' => 'Facturation', 'primaire' => false],
                    ] as $link)
                        <a href="{{ route($link['route']) }}"
                           @class([
                               'flex flex-col items-center gap-1 rounded-2xl p-3 text-center transition',
                               'brio-btn-primary !flex-col !rounded-2xl !py-3' => $link['primaire'],
                               'brio-admin-tile' => ! $link['primaire'],
                           ])>
                            <span class="text-lg">{{ $link['icon'] }}</span>
                            <span class="text-[11px] font-semibold">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-app-card>
        </div>
    </div>
</div>
