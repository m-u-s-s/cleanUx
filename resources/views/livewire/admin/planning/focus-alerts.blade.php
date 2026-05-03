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
