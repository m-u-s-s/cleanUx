<x-guest-layout>
    {{--
      Page LOGIN — vitrine (cx-shell sombre)
      Backend Fortify préservé : POST {{ route('login') }} + @csrf + champs email/password/remember
      Composants Jetstream remplacés par du HTML brut dans .cx-field pour zéro conflit avec le thème sombre.
      Animation : reveal sobre uniquement. Aucun scroll-storytelling sur une page formulaire.
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">

            {{-- ── PANNEAU MARQUE (gauche, desktop only) ─────────────────────── --}}
            <aside class="hidden lg:block" data-cx-reveal>
                <div class="cx-card relative overflow-hidden p-10">
                    <span class="cx-chip"><span class="pip"></span> Espace sécurisé</span>

                    <h1 class="cx-h mt-8 text-5xl lg:text-6xl">
                        Bon retour<br><span class="cx-gradient-text">parmi nous.</span>
                    </h1>
                    <p class="cx-lede mt-6 max-w-md text-base">
                        Vos rendez-vous, vos missions, vos factures et votre suivi en temps réel —
                        accessibles selon votre rôle.
                    </p>

                    <div class="mt-10 grid grid-cols-2 gap-3">
                        @foreach ([
                            ['Client','Réservations & suivi'],
                            ['Intervenant','Missions terrain'],
                            ['Entreprise','Multi-sites & factures'],
                            ['Admin','Pilotage complet'],
                        ] as $i => $r)
                            <div class="rounded-2xl border p-5"
                                 style="border-color:var(--cx-line);background:rgba(255,255,255,.03)"
                                 data-cx-reveal data-cx-delay="{{ $i }}">
                                <p class="text-xl font-extrabold" style="font-family:var(--cx-display);color:var(--cx-text)">{{ $r[0] }}</p>
                                <p class="mt-1 text-sm" style="color:var(--cx-muted)">{{ $r[1] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            {{-- ── FORMULAIRE ────────────────────────────────────────────────── --}}
            <div data-cx-reveal data-cx-delay="1">
                <div class="cx-card p-7 sm:p-10">
                    <p class="cx-kicker">Connexion</p>
                    <h2 class="cx-h mt-3 text-3xl sm:text-4xl">Accédez à votre <span class="cx-gradient-text">espace.</span></h2>
                    <p class="cx-lede mt-3 text-sm">
                        Connectez-vous selon votre rôle : client, intervenant, entreprise ou admin.
                    </p>

                    {{-- Erreurs Fortify --}}
                    <x-validation-errors class="mt-6" />

                    @if (session('status'))
                        <div class="mt-6 rounded-2xl border p-4 text-sm"
                             style="border-color:rgba(79,227,214,.35);background:rgba(79,227,214,.08);color:var(--cx-cyan)">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                        @csrf

                        <div class="cx-field">
                            <label for="email">Adresse e-mail</label>
                            <input id="email" name="email" type="email" required autofocus
                                   autocomplete="username" placeholder="exemple@email.com"
                                   value="{{ old('email') }}">
                        </div>

                        <div class="cx-field">
                            <div class="flex items-center justify-between">
                                <label for="password" class="!mb-0">Mot de passe</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}"
                                       class="text-xs font-bold uppercase tracking-[0.14em]" style="color:var(--cx-amber)">
                                        Oublié ?
                                    </a>
                                @endif
                            </div>
                            <input id="password" name="password" type="password" required
                                   autocomplete="current-password" placeholder="••••••••"
                                   class="!mt-3">
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border p-4"
                               style="border-color:var(--cx-line);background:rgba(255,255,255,.03)">
                            <input type="checkbox" name="remember" id="remember_me"
                                   style="accent-color:var(--cx-amber);height:18px;width:18px">
                            <span class="text-sm" style="color:var(--cx-muted)">Rester connecté sur cet appareil</span>
                            <span class="ml-auto hidden text-xs uppercase tracking-[0.14em] sm:inline" style="color:var(--cx-muted)">
                                Sécurisé
                            </span>
                        </label>

                        <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                            Se connecter →
                        </button>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--ghost w-full px-4 py-3 text-sm">
                                Réserver sans compte
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="cx-btn cx-btn--ghost w-full px-4 py-3 text-sm"
                                   style="border-color:rgba(255,182,72,.35);color:var(--cx-amber)">
                                    Créer un compte
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-8 rounded-2xl border p-5"
                         style="border-color:var(--cx-line);background:rgba(255,255,255,.025)">
                        <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:var(--cx-amber)">Dans votre espace</p>
                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2" style="color:var(--cx-muted)">
                            <p>✓ Vos rendez-vous</p>
                            <p>✓ Suivi de mission live</p>
                            <p>✓ Factures &amp; documents</p>
                            <p>✓ Feedback &amp; preuves</p>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </main>
</x-guest-layout>