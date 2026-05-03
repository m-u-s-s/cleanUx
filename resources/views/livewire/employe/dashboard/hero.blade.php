        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.85fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-300">
                        Portail employé
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Ma journée terrain
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Vue rapide de vos missions, priorités, zones, actions terrain et historique récent.
                        L’objectif est simple : savoir quoi faire maintenant, où aller et quoi clôturer.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        @if(Route::has('employe.missions'))
                            <a href="{{ route('employe.missions') }}" class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                                📋 Toutes mes missions
                            </a>
                        @endif

                        @if(Route::has('employe.planning'))
                            <a href="{{ route('employe.planning') }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                📅 Mon planning
                            </a>
                        @endif

                        @if(Route::has('employe.historique'))
                            <a href="{{ route('employe.historique') }}" class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                🕘 Historique
                            </a>
                        @endif

                        @if(Route::has('employe.incident'))
                            <a href="{{ route('employe.incident') }}" class="rounded-2xl border border-rose-300/30 bg-rose-400/10 px-4 py-2 text-sm font-bold text-rose-100 transition hover:bg-rose-400/20">
                                ⚠️ Signaler incident
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                            Aujourd’hui
                        </p>
                        <p class="mt-2 text-3xl font-black text-white">
                            {{ $statsJour['total'] }} mission(s)
                        </p>
                        <p class="mt-1 text-sm text-slate-200">
                            {{ $statsJour['heures_prevues'] }} h prévues · {{ $statsJour['progression'] }}% terminé
                        </p>

                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/20">
                            <div class="h-full rounded-full bg-emerald-400" style="width: {{ min(100, max(0, $statsJour['progression'])) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-3xl border {{ $paymentStatus['ready'] ? 'border-emerald-300/20 bg-emerald-400/10' : 'border-amber-300/20 bg-amber-400/10' }} p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $paymentStatus['ready'] ? 'text-emerald-200' : 'text-amber-200' }}">
                            Paiement prestataire
                        </p>

                        <p class="mt-2 text-xl font-black text-white">
                            {{ $paymentStatus['label'] }}
                        </p>

                        @if(! $paymentStatus['ready'] && Route::has('employe.stripe-connect.start'))
                            <a href="{{ route('employe.stripe-connect.start') }}" class="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 hover:bg-slate-100">
                                Configurer mes paiements
                            </a>
                        @else
                            <p class="mt-2 text-sm text-emerald-50">
                                Votre compte est prêt pour recevoir les reversements.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
