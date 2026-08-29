@push('scripts')
    @vite(['resources/js/apexcharts.js'])
@endpush

<div class="space-y-6">

    {{-- La page recopiait le systeme en Tailwind brut : en-tete, cases et etat vide maison.
         Elle emploie desormais les memes composants que les autres tableaux de bord. --}}
    <x-page-shell
        eyebrow="Tableau de bord"
        :title="Auth::user()->currentOrganization?->name ?? __('Ma société')"
        subtitle="Missions du jour, équipe et alertes de votre société.">

        <x-slot name="actions">
            @foreach (['today' => "Aujourd'hui", 'week' => 'Semaine', 'month' => 'Mois'] as $val => $label)
                <button type="button" wire:click="$set('period', '{{ $val }}')"
                    class="{{ $period === $val ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                    {{ $label }}
                </button>
            @endforeach
        </x-slot>

        @if (!empty($alerts))
            <div class="space-y-2">
                @foreach ($alerts as $alert)
                    <div class="brio-alerte !mb-0 {{ $alert['level'] === 'red' ? 'brio-alerte-danger' : 'brio-alerte-warning' }}">
                        <span class="text-lg leading-none">{{ $alert['icon'] }}</span>
                        <span class="font-medium">{{ $alert['message'] }}</span>
                        {{-- Route nulle : l'alerte concerne l'appelant, la page qui la traite non. --}}
                        @if ($alert['route'])
                            <a href="{{ route($alert['route']) }}" class="ms-auto text-xs underline opacity-70 hover:opacity-100">
                                Voir →
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-page-shell>

    {{-- ── KPIs ── --}}
    @php
        $kpiCards = [
            ['value' => $kpis['missions_today'],   'label' => "Missions aujourd'hui", 'icon' => '📋', 'color' => 'blue'],
            ['value' => $kpis['missions_active'],  'label' => 'En cours',             'icon' => '🔄', 'color' => 'green'],
            ['value' => $kpis['missions_done'],    'label' => 'Terminées',            'icon' => '✅', 'color' => 'emerald'],
            ['value' => $kpis['missions_delayed'], 'label' => 'En retard',            'icon' => '⚠️', 'color' => 'red'],
            ['value' => $kpis['members_active'],   'label' => 'Membres actifs',       'icon' => '👥', 'color' => 'purple'],
            ['value' => $kpis['pending_tasks'],    'label' => 'Tâches ouvertes',      'icon' => '📌', 'color' => 'orange'],
        ];

        /* Une carte sans valeur est RETIREE, pas affichee a zero : « Membres actifs » vaut null
           pour qui n'a pas `team.view`, et un zero se lirait comme un fait. */
        $kpiCards = array_values(array_filter($kpiCards, fn ($c) => $c['value'] !== null));
    @endphp

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($kpiCards as $card)
            <x-ui.stat
                :title="$card['label']"
                :value="$card['value']"
                :icon="$card['icon']"
                :tone="$card['color']"
                :hint="$card['label'] === 'En retard' && $card['value'] > 0 ? 'À traiter' : null" />
        @endforeach
    </div>

    @if ($this->totalActivite > 0)
        <section class="brio-graphique" aria-labelledby="titre-activite">
            <div class="brio-graphique-tete">
                <h2 id="titre-activite" class="brio-graphique-titre">{{ __('Activité de la société') }}</h2>
                <p class="brio-graphique-note">{{ __('Missions terminées, 14 derniers jours') }}</p>
            </div>

            <div class="brio-graphique-corps" wire:ignore x-data x-init="dessinerActivite($el)">
                <div data-graphique
                     data-nom="{{ __('Missions terminées') }}"
                     data-totaux="{{ json_encode(array_column($this->activiteParJour, 'total')) }}"
                     data-libelles="{{ json_encode(array_column($this->activiteParJour, 'libelle')) }}"></div>
            </div>
        </section>
    @endif

    {{-- ── Grille principale ── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Missions du jour --}}
        <x-app-card class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="brio-section-title">📋 Missions du jour ({{ $missionsDay->count() }})</h3>
                <a href="{{ route('provider-company.dispatch') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800">
                    Voir tout →
                </a>
            </div>

            <div class="space-y-2">
                @forelse ($missionsDay as $mission)
                    @php
                        $statusTones = [
                            'scheduled'   => 'neutral',
                            'dispatched'  => 'info',
                            'in_progress' => 'success',
                            'completed'   => 'success',
                            'cancelled'   => 'danger',
                        ];
                        $statusLabels = [
                            'scheduled'   => 'Planifiée',
                            'dispatched'  => 'Dispatchée',
                            'in_progress' => '🔄 En cours',
                            'completed'   => '✅ Terminée',
                            'cancelled'   => '❌ Annulée',
                        ];
                        $tone = $statusTones[$mission->status] ?? 'neutral';
                        $label = $statusLabels[$mission->status] ?? $mission->status;
                    @endphp
                    <div class="brio-list-item flex items-center gap-3 !p-3">
                        <div class="min-w-[44px] text-center">
                            <p class="text-sm font-black text-slate-900">
                                {{ $mission->planned_start_at?->format('H:i') ?? '—' }}
                            </p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ $mission->reference ?? 'Mission #' . $mission->id }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $mission->booking?->organizationSite?->fullAddress() ?? $mission->address ?? '—' }}
                            </p>
                        </div>
                        @if ($mission->leadProvider)
                            <img src="{{ $mission->leadProvider->profile_photo_url }}"
                                 alt="{{ $mission->leadProvider->name }}"
                                 title="{{ $mission->leadProvider->name }}"
                                 class="h-7 w-7 flex-shrink-0 rounded-full border border-slate-300 object-cover">
                        @else
                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-dashed border-slate-300 text-xs text-slate-400"
                                 title="Non assigné">?</div>
                        @endif
                        <x-ui.badge :tone="$tone" :label="$label" class="flex-shrink-0" />
                    </div>
                @empty
                    <x-empty-state
                        icon="📋"
                        title="Aucune mission planifiée aujourd'hui"
                        message="Les missions du jour de votre société apparaîtront ici dès qu'une réservation sera planifiée." />
                @endforelse
            </div>
        </x-app-card>

        {{-- Équipe et accès rapides --}}
        <div class="space-y-4">
            {{-- Le trombinoscope et son lien « Gerer » relevent de `team.view` : sans elle, l'ecran
                 Equipe repond 403, et le bloc serait deux promesses non tenues au lieu d'une. --}}
            @if ($peutVoirLEquipe)
                <x-app-card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="brio-section-title">👥 Équipe ({{ $teamStatus->count() }})</h3>
                        <a href="{{ route('provider-company.team') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800">
                            Gérer →
                        </a>
                    </div>

                    <div class="space-y-2">
                        @forelse ($teamStatus as $member)
                            @php
                                $dot = match ($member['status']) {
                                    'in_mission' => 'bg-emerald-500',
                                    'available'  => 'bg-emerald-400',
                                    default      => 'bg-slate-600',
                                };
                            @endphp
                            <div class="brio-list-item flex items-center gap-3 !p-2.5">
                                <div class="relative flex-shrink-0">
                                    <img src="{{ $member['avatar'] }}"
                                         alt="{{ $member['name'] }}"
                                         class="h-8 w-8 rounded-full object-cover">
                                    <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white {{ $dot }}"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $member['name'] }}</p>
                                    <p class="text-[10px] text-slate-500">{{ $member['role'] }}</p>
                                </div>
                                <span class="text-[10px] text-slate-500">
                                    {{ $member['status'] === 'in_mission' ? '🔄' : ($member['status'] === 'available' ? '✓' : '–') }}
                                </span>
                            </div>
                        @empty
                            <p class="px-1 py-3 text-sm text-slate-500">{{ __('Aucun membre pour le moment.') }}</p>
                        @endforelse
                    </div>
                </x-app-card>
            @endif

            <x-app-card title="Accès rapides">
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('provider-company.channels') }}"
                       class="brio-admin-tile flex flex-col items-center gap-1 !p-3 text-center">
                        <span class="text-xl">💬</span>
                        <span class="text-[10px] font-semibold text-slate-600">Canaux</span>
                    </a>
                    <a href="{{ route('provider-company.tasks') }}"
                       class="brio-admin-tile flex flex-col items-center gap-1 !p-3 text-center">
                        <span class="text-xl">📌</span>
                        <span class="text-[10px] font-semibold text-slate-600">Tâches</span>
                    </a>
                    {{-- Canaux et Taches restent ouverts a tous. Dispatch et Equipe portent la meme
                         cle que leur case de navbar, que `ModuleCatalogue` retire deja a un worker. --}}
                    @if ($peutRepartir)
                        <a href="{{ route('provider-company.dispatch') }}"
                           class="brio-admin-tile flex flex-col items-center gap-1 !p-3 text-center">
                            <span class="text-xl">🗺️</span>
                            <span class="text-[10px] font-semibold text-slate-600">Dispatch</span>
                        </a>
                    @endif
                    @if ($peutVoirLEquipe)
                        <a href="{{ route('provider-company.team') }}"
                           class="brio-admin-tile flex flex-col items-center gap-1 !p-3 text-center">
                            <span class="text-xl">👥</span>
                            <span class="text-[10px] font-semibold text-slate-600">Équipe</span>
                        </a>
                    @endif
                </div>
            </x-app-card>
        </div>
    </div>
</div>
