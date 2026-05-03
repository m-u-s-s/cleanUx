        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-800 to-slate-900 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.75fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">
                        Portail terrain
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Mes missions
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Suivez vos rendez-vous, ouvrez une mission, consultez les checklists, gérez le terrain,
                        le tracking et les incidents depuis une seule page.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        @if(Route::has('employe.dashboard'))
                            <a href="{{ route('employe.dashboard') }}"
                               class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                                ← Dashboard
                            </a>
                        @endif

                        @if(Route::has('employe.planning'))
                            <a href="{{ route('employe.planning') }}"
                               class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                📅 Planning
                            </a>
                        @endif

                        @if(Route::has('employe.historique'))
                            <a href="{{ route('employe.historique') }}"
                               class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                                🕘 Historique
                            </a>
                        @endif

                        @if(Route::has('employe.incident'))
                            <a href="{{ route('employe.incident') }}"
                               class="rounded-2xl border border-rose-300/30 bg-rose-400/10 px-4 py-2 text-sm font-bold text-rose-100 transition hover:bg-rose-400/20">
                                ⚠️ Signaler un incident
                            </a>
                        @endif
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                        Lecture rapide
                    </p>

                    <ul class="mt-3 space-y-2 text-sm text-slate-100">
                        <li>• <span class="font-semibold">À confirmer</span> = missions encore à valider</li>
                        <li>• <span class="font-semibold">À faire</span> = missions prêtes ou en cours</li>
                        <li>• <span class="font-semibold">Mission ouverte</span> = panneau terrain actif</li>
                        <li>• <span class="font-semibold">Checklists</span> = tâches à valider sur place</li>
                    </ul>
                </div>
            </div>
        </section>
