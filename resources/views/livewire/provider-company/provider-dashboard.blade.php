<div>

    {{--
        L'EN-TÊTE REPREND CELUI DE LA CONSOLE D'ADMINISTRATION.

        `ui-page-eyebrow` / `ui-page-title` / `ui-page-subtitle` sont les classes qu'emploie
        `livewire/admin/dashboard/cockpit-hero.blade.php`. Les recopier en Tailwind brut — un
        `text-2xl font-black` ici, un autre là — était précisément ce qui faisait dériver les deux
        espaces : une taille de titre changée d'un côté ne suivait pas de l'autre.
    --}}
    <div class="ui-page-header">
        <div>
            <p class="ui-page-eyebrow">Tableau de bord</p>
            <h1 class="ui-page-title">{{ Auth::user()->currentOrganization?->name }}</h1>
            <p class="ui-page-subtitle">Missions du jour, équipe et alertes de votre société.</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach (['today' => "Aujourd'hui", 'week' => 'Semaine', 'month' => 'Mois'] as $val => $label)
                <button wire:click="$set('period', '{{ $val }}')"
                    class="rounded-xl px-3 py-1.5 text-xs font-semibold transition
                        {{ $period === $val
                            ? 'bg-sky-600 text-white'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── Alertes ── --}}
    @if (!empty($alerts))
        <div class="mb-6 space-y-2">
            @foreach ($alerts as $alert)
                <div class="flex items-center gap-3 rounded-xl px-4 py-3
                    {{ $alert['level'] === 'red'
                        ? 'bg-red-50 border border-red-200 text-red-700'
                        : 'bg-amber-50 border border-amber-200 text-amber-700' }}">
                    <span class="text-lg">{{ $alert['icon'] }}</span>
                    <span class="text-sm font-medium">{{ $alert['message'] }}</span>
                    {{-- Route nulle : l'alerte concerne l'appelant, la page qui la traite non. --}}
                    @if ($alert['route'])
                        <a href="{{ route($alert['route']) }}"
                           class="ml-auto text-xs underline opacity-70 hover:opacity-100">
                            Voir →
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── KPIs ── --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @php
            $kpiCards = [
                ['value' => $kpis['missions_today'],   'label' => "Missions aujourd'hui", 'icon' => '📋', 'color' => 'blue'],
                ['value' => $kpis['missions_active'],  'label' => 'En cours',             'icon' => '🔄', 'color' => 'green'],
                ['value' => $kpis['missions_done'],    'label' => 'Terminées',            'icon' => '✅', 'color' => 'emerald'],
                ['value' => $kpis['missions_delayed'], 'label' => 'En retard',            'icon' => '⚠️', 'color' => 'red'],
                ['value' => $kpis['members_active'],   'label' => 'Membres actifs',       'icon' => '👥', 'color' => 'purple'],
                ['value' => $kpis['pending_tasks'],    'label' => 'Tâches ouvertes',      'icon' => '📌', 'color' => 'orange'],
            ];

            /*
             * Une carte sans valeur est RETIRÉE, pas affichée à zéro. « Membres actifs » vaut null
             * pour qui n'a pas `team.view` : un zéro se lirait comme un fait, et il est faux.
             */
            $kpiCards = array_values(array_filter($kpiCards, fn ($c) => $c['value'] !== null));
        @endphp

        @foreach ($kpiCards as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xl">{{ $card['icon'] }}</span>
                    @if ($card['value'] > 0 && $card['label'] === 'En retard')
                        <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                    @endif
                </div>
                <p class="text-2xl font-black text-slate-900">{{ $card['value'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Grille principale ── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Missions du jour --}}
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">
                    📋 Missions du jour ({{ $missionsDay->count() }})
                </h2>
                <a href="{{ route('provider-company.dispatch') }}"
                   class="text-xs text-blue-600 hover:text-blue-800">
                    Voir tout →
                </a>
            </div>

            <div class="space-y-2">
                @forelse ($missionsDay as $mission)
                    @php
                        $statusColors = [
                            'scheduled'   => 'bg-slate-100 text-slate-600',
                            'dispatched'  => 'bg-blue-50 text-blue-700',
                            'in_progress' => 'bg-emerald-50 text-emerald-700',
                            'completed'   => 'bg-emerald-50 text-emerald-600',
                            'cancelled'   => 'bg-red-50 text-red-600',
                        ];
                        $statusLabels = [
                            'scheduled'   => 'Planifiée',
                            'dispatched'  => 'Dispatchée',
                            'in_progress' => '🔄 En cours',
                            'completed'   => '✅ Terminée',
                            'cancelled'   => '❌ Annulée',
                        ];
                        $color = $statusColors[$mission->status] ?? 'bg-slate-100 text-slate-600';
                        $label = $statusLabels[$mission->status] ?? $mission->status;
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-center min-w-[44px]">
                            <p class="text-sm font-black text-slate-900">
                                {{ $mission->planned_start_at?->format('H:i') ?? '—' }}
                            </p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ $mission->reference ?? 'Mission #' . $mission->id }}
                            </p>
                            <p class="text-xs text-slate-500 truncate">
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
                        <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $color }}">
                            {{ $label }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 py-10 text-center text-slate-400">
                        <p class="text-3xl mb-2">📋</p>
                        <p class="text-sm">Aucune mission planifiée aujourd'hui</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Statut équipe --}}
        <div>
            {{--
                Le trombinoscope et son lien « Gérer » relèvent de `team.view` : sans elle, le
                composant ne charge aucun membre et l'écran Équipe répond 403. Afficher un bloc vide
                surmonté d'un lien interdit serait deux promesses non tenues au lieu d'une.
            --}}
            @if ($peutVoirLEquipe)
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">
                    👥 Équipe ({{ $teamStatus->count() }})
                </h2>
                <a href="{{ route('provider-company.team') }}"
                   class="text-xs text-blue-600 hover:text-blue-800">
                    Gérer →
                </a>
            </div>

            <div class="space-y-2">
                @foreach ($teamStatus as $member)
                    @php
                        $dot = match ($member['status']) {
                            'in_mission' => 'bg-emerald-500',
                            'available'  => 'bg-emerald-400',
                            default      => 'bg-slate-600',
                        };
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                        <div class="relative flex-shrink-0">
                            <img src="{{ $member['avatar'] }}"
                                 alt="{{ $member['name'] }}"
                                 class="h-8 w-8 rounded-full object-cover">
                            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-slate-100 {{ $dot }}"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $member['name'] }}</p>
                            <p class="text-[10px] text-slate-500">{{ $member['role'] }}</p>
                        </div>
                        <span class="text-[10px] text-slate-500">
                            {{ $member['status'] === 'in_mission' ? '🔄' : ($member['status'] === 'available' ? '✓' : '–') }}
                        </span>
                    </div>
                @endforeach
            </div>
            @endif

            {{-- Liens rapides --}}
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ route('provider-company.channels') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 bg-white p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-50">
                    <span class="text-xl">💬</span>
                    <span class="text-[10px] font-semibold text-slate-600">Canaux</span>
                </a>
                <a href="{{ route('provider-company.tasks') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 bg-white p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-50">
                    <span class="text-xl">📌</span>
                    <span class="text-[10px] font-semibold text-slate-600">Tâches</span>
                </a>
                {{--
                    Canaux et Tâches restent ouverts à tous — ce sont les deux écrans que le lot 1
                    laisse à un exécutant. Dispatch et Équipe portent la même clé que leur case de
                    navbar : `ModuleCatalogue` les retire déjà du menu d'un worker, ces deux
                    raccourcis étaient la dernière porte à mener nulle part.
                --}}
                @if ($peutRepartir)
                <a href="{{ route('provider-company.dispatch') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 bg-white p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-50">
                    <span class="text-xl">🗺️</span>
                    <span class="text-[10px] font-semibold text-slate-600">Dispatch</span>
                </a>
                @endif
                @if ($peutVoirLEquipe)
                <a href="{{ route('provider-company.team') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 bg-white p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-50">
                    <span class="text-xl">👥</span>
                    <span class="text-[10px] font-semibold text-slate-600">Équipe</span>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
