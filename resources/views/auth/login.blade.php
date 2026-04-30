<x-guest-layout>
    <main class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <section class="mx-auto grid max-w-7xl grid-cols-1 gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-16">

            {{-- LEFT --}}
            <div class="hidden lg:flex flex-col justify-between rounded-[2rem] bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-10 text-white shadow-2xl">
                <div>
                    <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-blue-100">
                        Espace sécurisé
                    </span>

                    <h1 class="mt-8 text-5xl font-black leading-tight">
                        Connectez-vous à votre espace CleanUx.
                    </h1>

                    <p class="mt-5 text-lg leading-8 text-slate-300">
                        Gérez vos rendez-vous, vos missions, vos factures, vos feedbacks et votre suivi en temps réel.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">Client</p>
                        <p class="mt-1 text-sm text-slate-300">Réservations & suivi</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">Employé</p>
                        <p class="mt-1 text-sm text-slate-300">Missions terrain</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">Admin</p>
                        <p class="mt-1 text-sm text-slate-300">Pilotage complet</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">B2B</p>
                        <p class="mt-1 text-sm text-slate-300">Sites & factures</p>
                    </div>
                </div>
            </div>

            {{-- FORM --}}
            <div class="flex items-center">
                <div class="w-full rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
                    <div class="mb-6">
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-600">
                            Connexion
                        </p>

                        <h2 class="mt-2 text-3xl font-black text-slate-900">
                            Bon retour 👋
                        </h2>

                        <p class="mt-2 text-sm text-slate-500">
                            Accédez à votre dashboard selon votre rôle : client, employé, entreprise ou admin.
                        </p>
                    </div>

                    <x-validation-errors class="mb-4" />

                    @if (session('status'))
                        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <div>
                            <x-label for="email" value="Adresse e-mail" />
                            <x-input id="email"
                                     class="mt-1 block w-full rounded-xl"
                                     type="email"
                                     name="email"
                                     :value="old('email')"
                                     required
                                     autofocus
                                     autocomplete="username"
                                     placeholder="exemple@email.com" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-label for="password" value="Mot de passe" />

                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}"
                                       class="text-xs font-bold text-blue-600 hover:text-blue-700">
                                        Mot de passe oublié ?
                                    </a>
                                @endif
                            </div>

                            <x-input id="password"
                                     class="mt-1 block w-full rounded-xl"
                                     type="password"
                                     name="password"
                                     required
                                     autocomplete="current-password"
                                     placeholder="••••••••" />
                        </div>

                        <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                            <label for="remember_me" class="flex items-center">
                                <x-checkbox id="remember_me" name="remember" />
                                <span class="ms-2 text-sm font-medium text-slate-600">
                                    Rester connecté
                                </span>
                            </label>

                            <span class="hidden text-xs text-slate-400 sm:inline">
                                Connexion sécurisée
                            </span>
                        </div>

                        <button type="submit"
                                class="flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-100 hover:bg-blue-700">
                            Se connecter
                        </button>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <a href="{{ route('booking.create') }}"
                               class="flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                                Réserver sans attendre
                            </a>

                            @if(Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="flex items-center justify-center rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-700 hover:bg-blue-100">
                                    Créer un compte
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-bold text-slate-900">
                            Pourquoi se connecter ?
                        </p>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600 sm:grid-cols-2">
                            <p>✅ Voir vos rendez-vous</p>
                            <p>✅ Suivre une mission</p>
                            <p>✅ Recevoir vos factures</p>
                            <p>✅ Laisser un feedback</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>