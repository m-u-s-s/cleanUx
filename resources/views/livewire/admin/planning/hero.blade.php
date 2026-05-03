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
