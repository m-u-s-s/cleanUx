<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-10 pt-6 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.4fr_0.8fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-300">Pilotage planning</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Centre de planification opérationnelle</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Cette page sert à comprendre rapidement la semaine, repérer les urgences, équilibrer la charge,
                        vérifier les affectations et naviguer vers les modules clés sans perdre le fil opérationnel.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button wire:click="allerAujourdHui" class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                            Aujourd’hui
                        </button>
                        <button wire:click="resetFiltres" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser les filtres
                        </button>
                        <a href="{{ route('admin.calendar') }}" class="rounded-2xl border border-sky-300/30 bg-sky-400/10 px-4 py-2 text-sm font-bold text-sky-100 transition hover:bg-sky-400/20">
                            Ouvrir le calendrier interne
                        </a>
                        <a href="{{ route('admin.missions') }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Voir toutes les missions
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Période</p>
                        <p class="mt-2 text-xl font-black text-white">{{ $weekSummary['window_label'] }}</p>
                        <p class="mt-1 text-sm text-slate-200">
                            Focus : {{ $focusDate->translatedFormat('l d F Y') }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">Compréhension rapide</p>
                        <ul class="mt-2 space-y-2 text-sm text-emerald-50">
                            <li>• <span class="font-semibold">Total</span> = charge planifiée sur la période filtrée</li>
                            <li>• <span class="font-semibold">Actifs</span> = missions encore à piloter</li>
                            <li>• <span class="font-semibold">Sans employé</span> = point d’action immédiat</li>
                            <li>• <span class="font-semibold">Charge équipe</span> = qui risque la saturation</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Filtres de pilotage</p>
                    <h2 class="text-2xl font-black text-slate-900">Affiner la période et le type d’intervention</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Les filtres ci-dessous impactent les KPIs, les alertes, la charge employé et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex items-center gap-2 self-start lg:self-auto">
                    <button wire:click="semainePrecedente" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">← Semaine précédente</button>
                    <button wire:click="semaineSuivante" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Semaine suivante →</button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Recherche globale</label>
                    <input type="text" wire:model.live.debounce.350ms="recherche" placeholder="Client, employé, ville, service, référence…" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Employé</label>
                    <select wire:model.live="filtreEmploye" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous</option>
                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Date focus</label>
                    <input type="date" wire:model.live="filtreDate" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Statut</label>
                    <select wire:model.live="filtreStatus" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Priorité</label>
                    <select wire:model.live="filtrePriorite" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes</option>
                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Charge totale</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $stats['total_hours'] }} h planifiées</p>
            </div>
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-emerald-700">Actifs à piloter</p>
                <p class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['active'] }}</p>
                <p class="mt-1 text-sm text-emerald-700">{{ $stats['confirme'] }} confirmés / {{ $stats['attente'] }} en attente</p>
            </div>
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-amber-700">Points chauds</p>
                <p class="mt-2 text-3xl font-black text-amber-900">{{ $stats['urgentes'] }}</p>
                <p class="mt-1 text-sm text-amber-700">{{ $stats['sans_employe'] }} sans employé</p>
            </div>
            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-blue-700">Couverture</p>
                <p class="mt-2 text-3xl font-black text-blue-900">{{ $stats['assigned_rate'] }}%</p>
                <p class="mt-1 text-sm text-blue-700">{{ $weekSummary['days_with_work'] }} jours chargés • {{ $weekSummary['entreprise_count'] }} rdv B2B</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Focus opérationnel</p>
                        <h2 class="text-2xl font-black text-slate-900">Interventions du jour ciblé</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Vue courte pour comprendre ce qui doit bouger en priorité sur la journée ciblée.
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ $focusDate->translatedFormat('D d/m') }}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($interventionsFocus as $rdv)
                        <x-rdv-planning-card :rdv="$rdv" />
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
                            Aucune intervention ne correspond au focus actuel.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[2rem] border border-rose-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">Points d’attention</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-900">Ce qui demande une action</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($pointsAttention as $rdv)
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-black text-slate-900">{{ $rdv->service_display_name }}</p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ optional($rdv->date)->format('d/m/Y') }} à {{ substr((string) $rdv->heure, 0, 5) }}
                                            @if($rdv->client) • {{ $rdv->client->name }} @endif
                                        </p>
                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $rdv->employe?->name ?? 'Aucun employé assigné' }}
                                            @if($rdv->ville) • {{ $rdv->ville }} @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <x-badge :status="$rdv->status" />
                                        <x-priority-badge :priority="$rdv->priorite" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                                Aucun point critique détecté avec les filtres actuels.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-wide text-sky-600">Charge équipe</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-900">Employés les plus sollicités</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($chargeEmployes as $entry)
                            <div class="rounded-2xl border {{ $entry['is_busy'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $entry['employe']->name }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $entry['count'] }} interventions • {{ $entry['hours'] }} h • {{ $entry['active_count'] }} actives
                                        </p>
                                    </div>
                                    @if($entry['urgent_count'] > 0)
                                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700">
                                            {{ $entry['urgent_count'] }} urgente(s)
                                        </span>
                                    @endif
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

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Agenda hebdomadaire</p>
                    <h2 class="text-2xl font-black text-slate-900">Vue semaine claire et compacte</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Chaque colonne te montre la charge du jour, les urgences, les missions non assignées et les rendez-vous principaux.
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
