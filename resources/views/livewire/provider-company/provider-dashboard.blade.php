<div class="min-h-screen bg-slate-900 text-slate-100 p-6">

    {{-- ── Header ── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Tableau de bord</p>
            <h1 class="text-2xl font-black text-white">
                {{ Auth::user()->currentOrganization?->name }}
            </h1>
        </div>
        <div class="flex items-center gap-2">
            @foreach (['today' => "Aujourd'hui", 'week' => 'Semaine', 'month' => 'Mois'] as $val => $label)
                <button wire:click="$set('period', '{{ $val }}')"
                    class="rounded-xl px-3 py-1.5 text-xs font-semibold transition
                        {{ $period === $val
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white' }}">
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
                        ? 'bg-red-900/40 border border-red-500/40 text-red-300'
                        : 'bg-amber-900/40 border border-amber-500/40 text-amber-300' }}">
                    <span class="text-lg">{{ $alert['icon'] }}</span>
                    <span class="text-sm font-medium">{{ $alert['message'] }}</span>
                    <a href="{{ route($alert['route']) }}"
                       class="ml-auto text-xs underline opacity-70 hover:opacity-100">
                        Voir →
                    </a>
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
        @endphp

        @foreach ($kpiCards as $card)
            <div class="rounded-2xl border border-slate-700 bg-slate-800 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xl">{{ $card['icon'] }}</span>
                    @if ($card['value'] > 0 && $card['label'] === 'En retard')
                        <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                    @endif
                </div>
                <p class="text-2xl font-black text-white">{{ $card['value'] }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Grille principale ── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Missions du jour --}}
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">
                    📋 Missions du jour ({{ $missionsDay->count() }})
                </h2>
                <a href="{{ route('provider-company.dispatch') }}"
                   class="text-xs text-blue-400 hover:text-blue-300">
                    Voir tout →
                </a>
            </div>

            <div class="space-y-2">
                @forelse ($missionsDay as $mission)
                    @php
                        $statusColors = [
                            'scheduled'   => 'bg-slate-700 text-slate-300',
                            'dispatched'  => 'bg-blue-900/60 text-blue-300',
                            'in_progress' => 'bg-green-900/60 text-green-300',
                            'completed'   => 'bg-emerald-900/40 text-emerald-400',
                            'cancelled'   => 'bg-red-900/40 text-red-400',
                        ];
                        $statusLabels = [
                            'scheduled'   => 'Planifiée',
                            'dispatched'  => 'Dispatchée',
                            'in_progress' => '🔄 En cours',
                            'completed'   => '✅ Terminée',
                            'cancelled'   => '❌ Annulée',
                        ];
                        $color = $statusColors[$mission->status] ?? 'bg-slate-700 text-slate-300';
                        $label = $statusLabels[$mission->status] ?? $mission->status;
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-800/60 px-4 py-3">
                        <div class="text-center min-w-[44px]">
                            <p class="text-sm font-black text-white">
                                {{ \Carbon\Carbon::parse($mission->scheduled_at)->format('H:i') }}
                            </p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">
                                {{ $mission->reference ?? 'Mission #' . $mission->id }}
                            </p>
                            <p class="text-xs text-slate-400 truncate">
                                {{ $mission->booking?->organizationSite?->fullAddress() ?? $mission->address ?? '—' }}
                            </p>
                        </div>
                        @if ($mission->assignedWorker)
                            <img src="{{ $mission->assignedWorker->profile_photo_url }}"
                                 alt="{{ $mission->assignedWorker->name }}"
                                 title="{{ $mission->assignedWorker->name }}"
                                 class="h-7 w-7 flex-shrink-0 rounded-full border border-slate-600 object-cover">
                        @else
                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-dashed border-slate-600 text-xs text-slate-500"
                                 title="Non assigné">?</div>
                        @endif
                        <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $color }}">
                            {{ $label }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-700 py-10 text-center text-slate-500">
                        <p class="text-3xl mb-2">📋</p>
                        <p class="text-sm">Aucune mission planifiée aujourd'hui</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Statut équipe --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">
                    👥 Équipe ({{ $teamStatus->count() }})
                </h2>
                <a href="{{ route('provider-company.team') }}"
                   class="text-xs text-blue-400 hover:text-blue-300">
                    Gérer →
                </a>
            </div>

            <div class="space-y-2">
                @foreach ($teamStatus as $member)
                    @php
                        $dot = match ($member['status']) {
                            'in_mission' => 'bg-green-500',
                            'available'  => 'bg-emerald-400',
                            default      => 'bg-slate-600',
                        };
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2.5">
                        <div class="relative flex-shrink-0">
                            <img src="{{ $member['avatar'] }}"
                                 alt="{{ $member['name'] }}"
                                 class="h-8 w-8 rounded-full object-cover">
                            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-slate-800 {{ $dot }}"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">{{ $member['name'] }}</p>
                            <p class="text-[10px] text-slate-400">{{ $member['role'] }}</p>
                        </div>
                        <span class="text-[10px] text-slate-400">
                            {{ $member['status'] === 'in_mission' ? '🔄' : ($member['status'] === 'available' ? '✓' : '–') }}
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Liens rapides --}}
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ route('provider-company.channels') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-700 bg-slate-800/60 p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-800">
                    <span class="text-xl">💬</span>
                    <span class="text-[10px] font-semibold text-slate-300">Canaux</span>
                </a>
                <a href="{{ route('provider-company.tasks') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-700 bg-slate-800/60 p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-800">
                    <span class="text-xl">📌</span>
                    <span class="text-[10px] font-semibold text-slate-300">Tâches</span>
                </a>
                <a href="{{ route('provider-company.dispatch') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-700 bg-slate-800/60 p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-800">
                    <span class="text-xl">🗺️</span>
                    <span class="text-[10px] font-semibold text-slate-300">Dispatch</span>
                </a>
                <a href="{{ route('provider-company.team') }}"
                   class="flex flex-col items-center gap-1 rounded-xl border border-slate-700 bg-slate-800/60 p-3 text-center transition hover:border-blue-500/50 hover:bg-slate-800">
                    <span class="text-xl">👥</span>
                    <span class="text-[10px] font-semibold text-slate-300">Équipe</span>
                </a>
            </div>
        </div>
    </div>
</div>
