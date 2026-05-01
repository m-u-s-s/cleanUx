<div data-livewire-root="admin-planning" class="contents">
<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-6 px-4 pb-10 pt-6 sm:px-6 lg:px-8">

        {{-- HERO --}}
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.65fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">
                        Planning opérationnel
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Centre de planification opérationnelle
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Pilote la semaine, détecte les urgences, équilibre la charge employés et vérifie les affectations
                        depuis une seule vue claire.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="allerAujourdHui"
                            class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-100">
                            Aujourd’hui
                        </button>

                        <button
                            type="button"
                            wire:click="resetFiltres"
                            class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser
                        </button>

                        @if(Route::has('admin.calendar'))
                            <a
                                href="{{ Route::has('admin.calendar') ? route('admin.calendar') : route('admin.planning') }}"
                                class="rounded-2xl border border-sky-300/30 bg-sky-400/10 px-4 py-2 text-sm font-bold text-sky-100 transition hover:bg-sky-400/20">
                                Calendrier interne
                            </a>
                        @endif

                        @if(Route::has('admin.missions'))
                            <a
                                href="{{ route('admin.missions') }}"
                                class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                Missions
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Période affichée
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $weekSummary['window_label'] ?? 'Semaine actuelle' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-200">
                            Focus : {{ $focusDate->translatedFormat('l d F Y') }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                            Lecture rapide
                        </p>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['total'] ?? 0 }}</p>
                                <p class="text-emerald-50">missions</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                                <p class="text-emerald-50">assignées</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['active'] ?? 0 }}</p>
                                <p class="text-emerald-50">actives</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['sans_employe'] ?? 0 }}</p>
                                <p class="text-emerald-50">sans employé</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FILTRES --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Filtres de pilotage
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner la charge opérationnelle
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Ces filtres adaptent les KPIs, les alertes, la charge équipe et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="semainePrecedente"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        ← Semaine précédente
                    </button>

                    <button
                        type="button"
                        wire:click="semaineSuivante"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Semaine suivante →
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Recherche globale
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.350ms="recherche"
                        placeholder="Client, employé, ville, service, référence…"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Employé
                    </label>

                    <select
                        wire:model.live="filtreEmploye"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les employés</option>

                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Date focus
                    </label>

                    <input
                        type="date"
                        wire:model.live="filtreDate"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Statut
                    </label>

                    <select
                        wire:model.live="filtreStatus"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les statuts</option>

                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Priorité
                    </label>

                    <select
                        wire:model.live="filtrePriorite"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes les priorités</option>

                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- KPIS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ number_format($stats['total_hours'] ?? 0, 1, ',', ' ') }} h planifiées
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xl">📊</div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                        <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ $stats['confirme'] ?? 0 }} confirmés / {{ $stats['attente'] ?? 0 }} en attente
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">✅</div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                        <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-amber-700">
                            {{ $stats['sans_employe'] ?? 0 }} sans employé
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🚨</div>
                </div>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Couverture</p>
                        <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ $weekSummary['days_with_work'] ?? 0 }} jours chargés • {{ $weekSummary['entreprise_count'] ?? 0 }} RDV B2B
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🧭</div>
                </div>
            </div>
        </section>

        {{-- FOCUS + ALERTES --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                            Focus opérationnel
                        </p>

                        <h2 class="text-2xl font-black text-slate-900">
                            Interventions du jour ciblé
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Les missions importantes du jour sélectionné, classées pour faciliter le pilotage.
                        </p>
                    </div>

                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ $focusDate->translatedFormat('D d/m') }}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($interventionsFocus as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <x-empty-state
                            title="Aucune intervention sur ce focus"
                            message="Aucune mission ne correspond à la date et aux filtres actuels."
                            icon="📆" />
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[2rem] border border-rose-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">
                                Points d’attention
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Ce qui demande une action
                            </h2>
                        </div>

                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700">
                            {{ $pointsAttention->count() }} alerte(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($pointsAttention as $rdv)
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                            à {{ substr((string) $rdv->heure, 0, 5) }}
                                            @if($rdv->client)
                                                • {{ $rdv->client->name }}
                                            @endif
                                        </p>

                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $rdv->employe?->name ?? 'Aucun employé assigné' }}
                                            @if($rdv->ville)
                                                • {{ $rdv->ville }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                                Aucun point critique détecté avec les filtres actuels.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-sky-600">
                                Charge équipe
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Employés les plus sollicités
                            </h2>
                        </div>

                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-black text-sky-700">
                            {{ $chargeEmployes->count() }} employé(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($chargeEmployes as $entry)
                            @php
                                $minutes = $entry['minutes'] ?? 0;
                                $barWidth = min(100, (int) round(($minutes / 480) * 100));
                            @endphp

                            <div class="rounded-2xl border {{ $entry['is_busy'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $entry['employe']->name }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            {{ $entry['count'] }} intervention(s) • {{ $entry['hours'] }} h • {{ $entry['active_count'] }} active(s)
                                        </p>
                                    </div>

                                    @if(($entry['urgent_count'] ?? 0) > 0)
                                        <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700">
                                            {{ $entry['urgent_count'] }} urgente(s)
                                        </span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">
                                            OK
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full {{ $entry['is_busy'] ? 'bg-amber-500' : 'bg-sky-500' }}"
                                        style="width: {{ $barWidth }}%">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Aucune charge employé à afficher sur cette période.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- AGENDA HEBDOMADAIRE --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Agenda hebdomadaire
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Vue semaine claire et compacte
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Visualise la charge par jour, les urgences, les missions sans employé et les rendez-vous principaux.
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
                </div>
            </div>

            <livewire:admin.agenda-hebdomadaire
                :semaine="$semaine"
                :employe-id="$filtreEmploye"
                :status="$filtreStatus"
                :priorite="$filtrePriorite"
                :recherche="$recherche"
                :focus-date="$focusDate->toDateString()"
                :key="'agenda-'.$semaine.'-'.$filtreEmploye.'-'.$filtreStatus.'-'.$filtrePriorite.'-'.md5($recherche.$focusDate->toDateString())"
            />
        </section>
    </div>
</div>
BLADEcat > resources/views/livewire/admin/planning-admin.blade.php <<'BLADE'
<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-6 px-4 pb-10 pt-6 sm:px-6 lg:px-8">

        {{-- HERO --}}
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.65fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">
                        Planning opérationnel
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Centre de planification opérationnelle
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Pilote la semaine, détecte les urgences, équilibre la charge employés et vérifie les affectations
                        depuis une seule vue claire.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="allerAujourdHui"
                            class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-100">
                            Aujourd’hui
                        </button>

                        <button
                            type="button"
                            wire:click="resetFiltres"
                            class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser
                        </button>

                        @if(Route::has('admin.calendar'))
                            <a
                                href="{{ Route::has('admin.calendar') ? route('admin.calendar') : route('admin.planning') }}"
                                class="rounded-2xl border border-sky-300/30 bg-sky-400/10 px-4 py-2 text-sm font-bold text-sky-100 transition hover:bg-sky-400/20">
                                Calendrier interne
                            </a>
                        @endif

                        @if(Route::has('admin.missions'))
                            <a
                                href="{{ route('admin.missions') }}"
                                class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                Missions
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Période affichée
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $weekSummary['window_label'] ?? 'Semaine actuelle' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-200">
                            Focus : {{ $focusDate->translatedFormat('l d F Y') }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                            Lecture rapide
                        </p>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['total'] ?? 0 }}</p>
                                <p class="text-emerald-50">missions</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                                <p class="text-emerald-50">assignées</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['active'] ?? 0 }}</p>
                                <p class="text-emerald-50">actives</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['sans_employe'] ?? 0 }}</p>
                                <p class="text-emerald-50">sans employé</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FILTRES --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Filtres de pilotage
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner la charge opérationnelle
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Ces filtres adaptent les KPIs, les alertes, la charge équipe et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="semainePrecedente"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        ← Semaine précédente
                    </button>

                    <button
                        type="button"
                        wire:click="semaineSuivante"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Semaine suivante →
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Recherche globale
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.350ms="recherche"
                        placeholder="Client, employé, ville, service, référence…"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Employé
                    </label>

                    <select
                        wire:model.live="filtreEmploye"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les employés</option>

                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Date focus
                    </label>

                    <input
                        type="date"
                        wire:model.live="filtreDate"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Statut
                    </label>

                    <select
                        wire:model.live="filtreStatus"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les statuts</option>

                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Priorité
                    </label>

                    <select
                        wire:model.live="filtrePriorite"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes les priorités</option>

                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- KPIS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ number_format($stats['total_hours'] ?? 0, 1, ',', ' ') }} h planifiées
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xl">📊</div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                        <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ $stats['confirme'] ?? 0 }} confirmés / {{ $stats['attente'] ?? 0 }} en attente
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">✅</div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                        <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-amber-700">
                            {{ $stats['sans_employe'] ?? 0 }} sans employé
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🚨</div>
                </div>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Couverture</p>
                        <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ $weekSummary['days_with_work'] ?? 0 }} jours chargés • {{ $weekSummary['entreprise_count'] ?? 0 }} RDV B2B
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🧭</div>
                </div>
            </div>
        </section>

        {{-- FOCUS + ALERTES --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                            Focus opérationnel
                        </p>

                        <h2 class="text-2xl font-black text-slate-900">
                            Interventions du jour ciblé
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Les missions importantes du jour sélectionné, classées pour faciliter le pilotage.
                        </p>
                    </div>

                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ $focusDate->translatedFormat('D d/m') }}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($interventionsFocus as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <x-empty-state
                            title="Aucune intervention sur ce focus"
                            message="Aucune mission ne correspond à la date et aux filtres actuels."
                            icon="📆" />
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[2rem] border border-rose-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">
                                Points d’attention
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Ce qui demande une action
                            </h2>
                        </div>

                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700">
                            {{ $pointsAttention->count() }} alerte(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($pointsAttention as $rdv)
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                            à {{ substr((string) $rdv->heure, 0, 5) }}
                                            @if($rdv->client)
                                                • {{ $rdv->client->name }}
                                            @endif
                                        </p>

                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $rdv->employe?->name ?? 'Aucun employé assigné' }}
                                            @if($rdv->ville)
                                                • {{ $rdv->ville }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                                Aucun point critique détecté avec les filtres actuels.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-sky-600">
                                Charge équipe
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Employés les plus sollicités
                            </h2>
                        </div>

                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-black text-sky-700">
                            {{ $chargeEmployes->count() }} employé(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($chargeEmployes as $entry)
                            @php
                                $minutes = $entry['minutes'] ?? 0;
                                $barWidth = min(100, (int) round(($minutes / 480) * 100));
                            @endphp

                            <div class="rounded-2xl border {{ $entry['is_busy'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $entry['employe']->name }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            {{ $entry['count'] }} intervention(s) • {{ $entry['hours'] }} h • {{ $entry['active_count'] }} active(s)
                                        </p>
                                    </div>

                                    @if(($entry['urgent_count'] ?? 0) > 0)
                                        <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700">
                                            {{ $entry['urgent_count'] }} urgente(s)
                                        </span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">
                                            OK
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full {{ $entry['is_busy'] ? 'bg-amber-500' : 'bg-sky-500' }}"
                                        style="width: {{ $barWidth }}%">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Aucune charge employé à afficher sur cette période.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- AGENDA HEBDOMADAIRE --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Agenda hebdomadaire
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Vue semaine claire et compacte
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Visualise la charge par jour, les urgences, les missions sans employé et les rendez-vous principaux.
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
                </div>
            </div>

            <livewire:admin.agenda-hebdomadaire
                :semaine="$semaine"
                :employe-id="$filtreEmploye"
                :status="$filtreStatus"
                :priorite="$filtrePriorite"
                :recherche="$recherche"
                :focus-date="$focusDate->toDateString()"
                :key="'agenda-'.$semaine.'-'.$filtreEmploye.'-'.$filtreStatus.'-'.$filtrePriorite.'-'.md5($recherche.$focusDate->toDateString())"
            />
        </section>
    </div>
</div>
BLADEcat > resources/views/livewire/admin/planning-admin.blade.php <<'BLADE'
<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-6 px-4 pb-10 pt-6 sm:px-6 lg:px-8">

        {{-- HERO --}}
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.65fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">
                        Planning opérationnel
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Centre de planification opérationnelle
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Pilote la semaine, détecte les urgences, équilibre la charge employés et vérifie les affectations
                        depuis une seule vue claire.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="allerAujourdHui"
                            class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-100">
                            Aujourd’hui
                        </button>

                        <button
                            type="button"
                            wire:click="resetFiltres"
                            class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser
                        </button>

                        @if(Route::has('admin.calendar'))
                            <a
                                href="{{ Route::has('admin.calendar') ? route('admin.calendar') : route('admin.planning') }}"
                                class="rounded-2xl border border-sky-300/30 bg-sky-400/10 px-4 py-2 text-sm font-bold text-sky-100 transition hover:bg-sky-400/20">
                                Calendrier interne
                            </a>
                        @endif

                        @if(Route::has('admin.missions'))
                            <a
                                href="{{ route('admin.missions') }}"
                                class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                Missions
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Période affichée
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $weekSummary['window_label'] ?? 'Semaine actuelle' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-200">
                            Focus : {{ $focusDate->translatedFormat('l d F Y') }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                            Lecture rapide
                        </p>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['total'] ?? 0 }}</p>
                                <p class="text-emerald-50">missions</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                                <p class="text-emerald-50">assignées</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['active'] ?? 0 }}</p>
                                <p class="text-emerald-50">actives</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['sans_employe'] ?? 0 }}</p>
                                <p class="text-emerald-50">sans employé</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FILTRES --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Filtres de pilotage
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner la charge opérationnelle
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Ces filtres adaptent les KPIs, les alertes, la charge équipe et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="semainePrecedente"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        ← Semaine précédente
                    </button>

                    <button
                        type="button"
                        wire:click="semaineSuivante"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Semaine suivante →
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Recherche globale
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.350ms="recherche"
                        placeholder="Client, employé, ville, service, référence…"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Employé
                    </label>

                    <select
                        wire:model.live="filtreEmploye"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les employés</option>

                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Date focus
                    </label>

                    <input
                        type="date"
                        wire:model.live="filtreDate"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Statut
                    </label>

                    <select
                        wire:model.live="filtreStatus"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les statuts</option>

                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Priorité
                    </label>

                    <select
                        wire:model.live="filtrePriorite"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes les priorités</option>

                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- KPIS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ number_format($stats['total_hours'] ?? 0, 1, ',', ' ') }} h planifiées
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xl">📊</div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                        <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ $stats['confirme'] ?? 0 }} confirmés / {{ $stats['attente'] ?? 0 }} en attente
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">✅</div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                        <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-amber-700">
                            {{ $stats['sans_employe'] ?? 0 }} sans employé
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🚨</div>
                </div>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Couverture</p>
                        <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ $weekSummary['days_with_work'] ?? 0 }} jours chargés • {{ $weekSummary['entreprise_count'] ?? 0 }} RDV B2B
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🧭</div>
                </div>
            </div>
        </section>

        {{-- FOCUS + ALERTES --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                            Focus opérationnel
                        </p>

                        <h2 class="text-2xl font-black text-slate-900">
                            Interventions du jour ciblé
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Les missions importantes du jour sélectionné, classées pour faciliter le pilotage.
                        </p>
                    </div>

                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ $focusDate->translatedFormat('D d/m') }}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($interventionsFocus as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <x-empty-state
                            title="Aucune intervention sur ce focus"
                            message="Aucune mission ne correspond à la date et aux filtres actuels."
                            icon="📆" />
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[2rem] border border-rose-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">
                                Points d’attention
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Ce qui demande une action
                            </h2>
                        </div>

                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700">
                            {{ $pointsAttention->count() }} alerte(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($pointsAttention as $rdv)
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                            à {{ substr((string) $rdv->heure, 0, 5) }}
                                            @if($rdv->client)
                                                • {{ $rdv->client->name }}
                                            @endif
                                        </p>

                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $rdv->employe?->name ?? 'Aucun employé assigné' }}
                                            @if($rdv->ville)
                                                • {{ $rdv->ville }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                                Aucun point critique détecté avec les filtres actuels.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-sky-600">
                                Charge équipe
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Employés les plus sollicités
                            </h2>
                        </div>

                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-black text-sky-700">
                            {{ $chargeEmployes->count() }} employé(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($chargeEmployes as $entry)
                            @php
                                $minutes = $entry['minutes'] ?? 0;
                                $barWidth = min(100, (int) round(($minutes / 480) * 100));
                            @endphp

                            <div class="rounded-2xl border {{ $entry['is_busy'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $entry['employe']->name }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            {{ $entry['count'] }} intervention(s) • {{ $entry['hours'] }} h • {{ $entry['active_count'] }} active(s)
                                        </p>
                                    </div>

                                    @if(($entry['urgent_count'] ?? 0) > 0)
                                        <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700">
                                            {{ $entry['urgent_count'] }} urgente(s)
                                        </span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">
                                            OK
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full {{ $entry['is_busy'] ? 'bg-amber-500' : 'bg-sky-500' }}"
                                        style="width: {{ $barWidth }}%">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Aucune charge employé à afficher sur cette période.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- AGENDA HEBDOMADAIRE --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Agenda hebdomadaire
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Vue semaine claire et compacte
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Visualise la charge par jour, les urgences, les missions sans employé et les rendez-vous principaux.
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
                </div>
            </div>

            <livewire:admin.agenda-hebdomadaire
                :semaine="$semaine"
                :employe-id="$filtreEmploye"
                :status="$filtreStatus"
                :priorite="$filtrePriorite"
                :recherche="$recherche"
                :focus-date="$focusDate->toDateString()"
                :key="'agenda-'.$semaine.'-'.$filtreEmploye.'-'.$filtreStatus.'-'.$filtrePriorite.'-'.md5($recherche.$focusDate->toDateString())"
            />
        </section>
    </div>
</div>
BLADEcat > resources/views/livewire/admin/planning-admin.blade.php <<'BLADE'
<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-6 px-4 pb-10 pt-6 sm:px-6 lg:px-8">

        {{-- HERO --}}
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.65fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">
                        Planning opérationnel
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Centre de planification opérationnelle
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Pilote la semaine, détecte les urgences, équilibre la charge employés et vérifie les affectations
                        depuis une seule vue claire.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="allerAujourdHui"
                            class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-100">
                            Aujourd’hui
                        </button>

                        <button
                            type="button"
                            wire:click="resetFiltres"
                            class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser
                        </button>

                        @if(Route::has('admin.calendar'))
                            <a
                                href="{{ Route::has('admin.calendar') ? route('admin.calendar') : route('admin.planning') }}"
                                class="rounded-2xl border border-sky-300/30 bg-sky-400/10 px-4 py-2 text-sm font-bold text-sky-100 transition hover:bg-sky-400/20">
                                Calendrier interne
                            </a>
                        @endif

                        @if(Route::has('admin.missions'))
                            <a
                                href="{{ route('admin.missions') }}"
                                class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                Missions
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Période affichée
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $weekSummary['window_label'] ?? 'Semaine actuelle' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-200">
                            Focus : {{ $focusDate->translatedFormat('l d F Y') }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                            Lecture rapide
                        </p>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['total'] ?? 0 }}</p>
                                <p class="text-emerald-50">missions</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                                <p class="text-emerald-50">assignées</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['active'] ?? 0 }}</p>
                                <p class="text-emerald-50">actives</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['sans_employe'] ?? 0 }}</p>
                                <p class="text-emerald-50">sans employé</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FILTRES --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Filtres de pilotage
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner la charge opérationnelle
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Ces filtres adaptent les KPIs, les alertes, la charge équipe et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="semainePrecedente"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        ← Semaine précédente
                    </button>

                    <button
                        type="button"
                        wire:click="semaineSuivante"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Semaine suivante →
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Recherche globale
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.350ms="recherche"
                        placeholder="Client, employé, ville, service, référence…"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Employé
                    </label>

                    <select
                        wire:model.live="filtreEmploye"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les employés</option>

                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Date focus
                    </label>

                    <input
                        type="date"
                        wire:model.live="filtreDate"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Statut
                    </label>

                    <select
                        wire:model.live="filtreStatus"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les statuts</option>

                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Priorité
                    </label>

                    <select
                        wire:model.live="filtrePriorite"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes les priorités</option>

                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- KPIS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ number_format($stats['total_hours'] ?? 0, 1, ',', ' ') }} h planifiées
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xl">📊</div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                        <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ $stats['confirme'] ?? 0 }} confirmés / {{ $stats['attente'] ?? 0 }} en attente
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">✅</div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                        <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-amber-700">
                            {{ $stats['sans_employe'] ?? 0 }} sans employé
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🚨</div>
                </div>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Couverture</p>
                        <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ $weekSummary['days_with_work'] ?? 0 }} jours chargés • {{ $weekSummary['entreprise_count'] ?? 0 }} RDV B2B
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🧭</div>
                </div>
            </div>
        </section>

        {{-- FOCUS + ALERTES --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                            Focus opérationnel
                        </p>

                        <h2 class="text-2xl font-black text-slate-900">
                            Interventions du jour ciblé
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Les missions importantes du jour sélectionné, classées pour faciliter le pilotage.
                        </p>
                    </div>

                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ $focusDate->translatedFormat('D d/m') }}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($interventionsFocus as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <x-empty-state
                            title="Aucune intervention sur ce focus"
                            message="Aucune mission ne correspond à la date et aux filtres actuels."
                            icon="📆" />
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[2rem] border border-rose-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">
                                Points d’attention
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Ce qui demande une action
                            </h2>
                        </div>

                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700">
                            {{ $pointsAttention->count() }} alerte(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($pointsAttention as $rdv)
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                            à {{ substr((string) $rdv->heure, 0, 5) }}
                                            @if($rdv->client)
                                                • {{ $rdv->client->name }}
                                            @endif
                                        </p>

                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $rdv->employe?->name ?? 'Aucun employé assigné' }}
                                            @if($rdv->ville)
                                                • {{ $rdv->ville }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                                Aucun point critique détecté avec les filtres actuels.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-sky-600">
                                Charge équipe
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Employés les plus sollicités
                            </h2>
                        </div>

                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-black text-sky-700">
                            {{ $chargeEmployes->count() }} employé(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($chargeEmployes as $entry)
                            @php
                                $minutes = $entry['minutes'] ?? 0;
                                $barWidth = min(100, (int) round(($minutes / 480) * 100));
                            @endphp

                            <div class="rounded-2xl border {{ $entry['is_busy'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $entry['employe']->name }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            {{ $entry['count'] }} intervention(s) • {{ $entry['hours'] }} h • {{ $entry['active_count'] }} active(s)
                                        </p>
                                    </div>

                                    @if(($entry['urgent_count'] ?? 0) > 0)
                                        <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700">
                                            {{ $entry['urgent_count'] }} urgente(s)
                                        </span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">
                                            OK
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full {{ $entry['is_busy'] ? 'bg-amber-500' : 'bg-sky-500' }}"
                                        style="width: {{ $barWidth }}%">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Aucune charge employé à afficher sur cette période.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- AGENDA HEBDOMADAIRE --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Agenda hebdomadaire
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Vue semaine claire et compacte
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Visualise la charge par jour, les urgences, les missions sans employé et les rendez-vous principaux.
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
                </div>
            </div>

            <livewire:admin.agenda-hebdomadaire
                :semaine="$semaine"
                :employe-id="$filtreEmploye"
                :status="$filtreStatus"
                :priorite="$filtrePriorite"
                :recherche="$recherche"
                :focus-date="$focusDate->toDateString()"
                :key="'agenda-'.$semaine.'-'.$filtreEmploye.'-'.$filtreStatus.'-'.$filtrePriorite.'-'.md5($recherche.$focusDate->toDateString())"
            />
        </section>
    </div>
</div>
BLADEcat > resources/views/livewire/admin/planning-admin.blade.php <<'BLADE'
<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-6 px-4 pb-10 pt-6 sm:px-6 lg:px-8">

        {{-- HERO --}}
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.65fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">
                        Planning opérationnel
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Centre de planification opérationnelle
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Pilote la semaine, détecte les urgences, équilibre la charge employés et vérifie les affectations
                        depuis une seule vue claire.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="allerAujourdHui"
                            class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 shadow-sm transition hover:bg-slate-100">
                            Aujourd’hui
                        </button>

                        <button
                            type="button"
                            wire:click="resetFiltres"
                            class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser
                        </button>

                        @if(Route::has('admin.calendar'))
                            <a
                                href="{{ Route::has('admin.calendar') ? route('admin.calendar') : route('admin.planning') }}"
                                class="rounded-2xl border border-sky-300/30 bg-sky-400/10 px-4 py-2 text-sm font-bold text-sky-100 transition hover:bg-sky-400/20">
                                Calendrier interne
                            </a>
                        @endif

                        @if(Route::has('admin.missions'))
                            <a
                                href="{{ route('admin.missions') }}"
                                class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                Missions
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Période affichée
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $weekSummary['window_label'] ?? 'Semaine actuelle' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-200">
                            Focus : {{ $focusDate->translatedFormat('l d F Y') }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                            Lecture rapide
                        </p>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['total'] ?? 0 }}</p>
                                <p class="text-emerald-50">missions</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                                <p class="text-emerald-50">assignées</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['active'] ?? 0 }}</p>
                                <p class="text-emerald-50">actives</p>
                            </div>

                            <div>
                                <p class="text-2xl font-black text-white">{{ $stats['sans_employe'] ?? 0 }}</p>
                                <p class="text-emerald-50">sans employé</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FILTRES --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Filtres de pilotage
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner la charge opérationnelle
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Ces filtres adaptent les KPIs, les alertes, la charge équipe et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="semainePrecedente"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        ← Semaine précédente
                    </button>

                    <button
                        type="button"
                        wire:click="semaineSuivante"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Semaine suivante →
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Recherche globale
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.350ms="recherche"
                        placeholder="Client, employé, ville, service, référence…"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Employé
                    </label>

                    <select
                        wire:model.live="filtreEmploye"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les employés</option>

                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Date focus
                    </label>

                    <input
                        type="date"
                        wire:model.live="filtreDate"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Statut
                    </label>

                    <select
                        wire:model.live="filtreStatus"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les statuts</option>

                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Priorité
                    </label>

                    <select
                        wire:model.live="filtrePriorite"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes les priorités</option>

                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- KPIS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ number_format($stats['total_hours'] ?? 0, 1, ',', ' ') }} h planifiées
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xl">📊</div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                        <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ $stats['confirme'] ?? 0 }} confirmés / {{ $stats['attente'] ?? 0 }} en attente
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">✅</div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                        <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-amber-700">
                            {{ $stats['sans_employe'] ?? 0 }} sans employé
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🚨</div>
                </div>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Couverture</p>
                        <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ $weekSummary['days_with_work'] ?? 0 }} jours chargés • {{ $weekSummary['entreprise_count'] ?? 0 }} RDV B2B
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/70 px-3 py-2 text-xl">🧭</div>
                </div>
            </div>
        </section>

        {{-- FOCUS + ALERTES --}}
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                            Focus opérationnel
                        </p>

                        <h2 class="text-2xl font-black text-slate-900">
                            Interventions du jour ciblé
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Les missions importantes du jour sélectionné, classées pour faciliter le pilotage.
                        </p>
                    </div>

                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ $focusDate->translatedFormat('D d/m') }}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($interventionsFocus as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <x-empty-state
                            title="Aucune intervention sur ce focus"
                            message="Aucune mission ne correspond à la date et aux filtres actuels."
                            icon="📆" />
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[2rem] border border-rose-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">
                                Points d’attention
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Ce qui demande une action
                            </h2>
                        </div>

                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700">
                            {{ $pointsAttention->count() }} alerte(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($pointsAttention as $rdv)
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $rdv->service_display_name ?: 'Service non précisé' }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ $rdv->date?->format('d/m/Y') ?? $rdv->date }}
                                            à {{ substr((string) $rdv->heure, 0, 5) }}
                                            @if($rdv->client)
                                                • {{ $rdv->client->name }}
                                            @endif
                                        </p>

                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $rdv->employe?->name ?? 'Aucun employé assigné' }}
                                            @if($rdv->ville)
                                                • {{ $rdv->ville }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                                Aucun point critique détecté avec les filtres actuels.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-sky-600">
                                Charge équipe
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                Employés les plus sollicités
                            </h2>
                        </div>

                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-black text-sky-700">
                            {{ $chargeEmployes->count() }} employé(s)
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($chargeEmployes as $entry)
                            @php
                                $minutes = $entry['minutes'] ?? 0;
                                $barWidth = min(100, (int) round(($minutes / 480) * 100));
                            @endphp

                            <div class="rounded-2xl border {{ $entry['is_busy'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ $entry['employe']->name }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            {{ $entry['count'] }} intervention(s) • {{ $entry['hours'] }} h • {{ $entry['active_count'] }} active(s)
                                        </p>
                                    </div>

                                    @if(($entry['urgent_count'] ?? 0) > 0)
                                        <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700">
                                            {{ $entry['urgent_count'] }} urgente(s)
                                        </span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">
                                            OK
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full {{ $entry['is_busy'] ? 'bg-amber-500' : 'bg-sky-500' }}"
                                        style="width: {{ $barWidth }}%">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                Aucune charge employé à afficher sur cette période.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- AGENDA HEBDOMADAIRE --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Agenda hebdomadaire
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Vue semaine claire et compacte
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Visualise la charge par jour, les urgences, les missions sans employé et les rendez-vous principaux.
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $weekStart->translatedFormat('d M') }} → {{ $weekEnd->translatedFormat('d M Y') }}
                </div>
            </div>

            <livewire:admin.agenda-hebdomadaire
                :semaine="$semaine"
                :employe-id="$filtreEmploye"
                :status="$filtreStatus"
                :priorite="$filtrePriorite"
                :recherche="$recherche"
                :focus-date="$focusDate->toDateString()"
                :key="'agenda-'.$semaine.'-'.$filtreEmploye.'-'.$filtreStatus.'-'.$filtrePriorite.'-'.md5($recherche.$focusDate->toDateString())"
            />
        </section>
    </div>
</div>
</div>
