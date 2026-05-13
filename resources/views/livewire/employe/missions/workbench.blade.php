        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(380px,0.85fr)]">
            <div class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                                Liste opérationnelle
                            </p>
                            <h2 class="text-2xl font-black text-slate-900">
                                Rendez-vous assignés
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Sélectionnez une mission pour ouvrir le panneau terrain à droite.
                            </p>
                        </div>

                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                            {{ $stats['avec_mission'] ?? 0 }} avec mission
                        </span>
                    </div>

                    @include('livewire.employe.mes-rendez-vous')
                </div>
            </div>

            <aside class="space-y-4">
                @if($selectedRendezVous && $selectedMission)
                <div class="rounded-[2rem] border border-indigo-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                                Mission sélectionnée
                            </p>

                            <h3 class="mt-1 text-xl font-black text-slate-900">
                                {{ $selectedRendezVous->service_display_name ?: 'Service non précisé' }}
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                RDV #{{ $selectedRendezVous->id }} · Mission #{{ $selectedMission->id }}
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="clearSelectedRdv"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50">
                            Fermer
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-badge :status="$selectedRendezVous->status" />
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700 ring-1 ring-indigo-100">
                            Mission : {{ $selectedMission->status }}
                        </span>
                        <x-priority-badge :priority="$selectedRendezVous->priorite" />
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-slate-500">Client</p>
                            <p class="mt-1 font-bold text-slate-900">
                                {{ $selectedRendezVous->client?->name ?? '—' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-slate-500">Heure</p>
                            <p class="mt-1 font-bold text-slate-900">
                                {{ substr((string) $selectedRendezVous->heure, 0, 5) ?: '—' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                            <p class="text-slate-500">Adresse</p>
                            <p class="mt-1 font-bold text-slate-900">
                                {{ $selectedRendezVous->adresse ?? '—' }}, {{ $selectedRendezVous->ville ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @if($selectedRendezVous->telephone_client)
                        <a href="tel:{{ $selectedRendezVous->telephone_client }}"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700">
                            📞 Appeler
                        </a>
                        @endif

                        @if($selectedRendezVous->adresse || $selectedRendezVous->ville)
                        <a
                            href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($selectedRendezVous->adresse ?? '') . ' ' . ($selectedRendezVous->ville ?? '')) }}"
                            target="_blank"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700">
                            📍 GPS
                        </a>
                        @endif
                    </div>
                </div>

                
                @if($selectedMission ?? null)
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">
                    Mission sélectionnée
                </p>
                @endif

                @if($selectedMission->checklists->isNotEmpty())
                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Checklists terrain
                    </p>

                    <div class="mt-4 space-y-4">
                        @foreach($selectedMission->checklists as $checklist)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="font-black text-slate-900">
                                {{ $checklist->template_name }}
                            </h3>

                            <div class="mt-3 space-y-2">
                                @foreach($checklist->items as $item)
                                <label class="flex items-center gap-3 rounded-xl bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-100">
                                    <input
                                        type="checkbox"
                                        onchange="toggleChecklistItem({{ $item->id }}, this.checked)"
                                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        {{ $item->status === 'done' ? 'checked' : '' }}>

                                    <span>
                                        {{ $item->label }}
                                        @if($item->is_required)
                                        <span class="font-black text-red-500">*</span>
                                        @endif
                                    </span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Actions mission
                    </p>

                    <livewire:employe.mission-actions
                        :mission="$selectedMission"
                        :key="'mission-actions-'.$selectedMission->id" />
                </div>

                @if(in_array($selectedMission->status, ['en_route', 'arrived', 'started', 'paused']))
                <div class="rounded-[2rem] border border-blue-200 bg-white p-5 shadow-sm">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Tracking trajet
                    </p>

                    <livewire:employe.mission-route-tracking
                        :mission="$selectedMission"
                        :key="'mission-route-'.$selectedMission->id" />
                </div>
                @endif

                @if(in_array($selectedMission->status, ['arrived', 'started', 'paused', 'completed']))
                <div class="rounded-[2rem] border border-emerald-200 bg-white p-5 shadow-sm">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-emerald-600">
                        Exécution terrain
                    </p>

                    <livewire:employe.mission-execution-board
                        :mission="$selectedMission"
                        :key="'mission-execution-'.$selectedMission->id" />
                </div>
                @endif

                <div class="rounded-[2rem] border border-rose-200 bg-white p-5 shadow-sm">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-rose-600">
                        Incidents & qualité
                    </p>

                    <livewire:employe.mission-incident-board
                        :mission="$selectedMission"
                        :key="'incident-board-'.$selectedMission->id" />
                </div>
                @else
                <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-3xl bg-slate-100 text-2xl">
                        📋
                    </div>

                    <p class="mt-4 text-lg font-black text-slate-800">
                        Aucune mission ouverte
                    </p>

                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Sélectionnez un rendez-vous avec une mission liée pour afficher le panneau terrain,
                        le tracking, les actions, les checklists et les incidents.
                    </p>
                </div>
                @endif
            </aside>
        </section>
        </div>