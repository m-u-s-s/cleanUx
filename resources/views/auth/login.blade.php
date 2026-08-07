<x-guest-layout>
    {{-- ============================================================
         LOGIN — refonte Stripe-style centrée (21/05/2026)
         Backend Fortify préservé : POST route('login') + @csrf
         ============================================================ --}}

    <main class="min-h-screen flex items-center justify-center bg-slate-50/40 px-4 py-12">
        <div class="w-full max-w-md">
            {{-- Logo + retour --}}
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-slate-700 hover:text-brand-600 transition">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white font-bold text-lg shadow-soft-sm">
                        Br
                    </span>
                    <span class="text-lg font-bold">{{ config('app.name', 'Brio') }}</span>
                </a>
            </div>

            {{-- Card formulaire --}}
            <div class="cu-glass rounded-2xl border border-slate-200/80 shadow-soft p-8">
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Bon retour parmi nous</h1>
                    <p class="mt-2 text-sm text-slate-500">Connectez-vous à votre espace</p>
                </div>

                {{-- Erreurs --}}
                <x-validation-errors class="mb-6" />

                @session('status')
                <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">
                    {{ $value }}
                </div>
                @endsession

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="ui-label">Email</label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="vous@exemple.com"
                               class="ui-input">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="ui-label !mb-0">Mot de passe</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                    Oublié ?
                                </a>
                            @endif
                        </div>
                        <input id="password"
                               type="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               placeholder="••••••••"
                               class="ui-input">
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-slate-600">Se souvenir de moi</span>
                    </label>

                    <x-ui.button type="submit" variant="amber" size="lg" class="w-full">
                        Se connecter
                    </x-ui.button>
                </form>

                {{-- Divider + register --}}
                <div class="my-6 flex items-center gap-3">
                    <div class="flex-1 border-t border-slate-200"></div>
                    <span class="text-xs uppercase tracking-wider text-slate-400">ou</span>
                    <div class="flex-1 border-t border-slate-200"></div>
                </div>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}"
                       class="block w-full text-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Créer un compte gratuit
                    </a>
                @endif
            </div>

            {{-- Trust bar --}}
            <div class="mt-6 text-center">
                <p class="text-xs text-slate-500 inline-flex items-center gap-2 justify-center">
                    <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-emerald-500" />
                    Connexion sécurisée TLS 1.3 · Données chiffrées
                </p>
            </div>
        </div>
    </main>
</x-guest-layout>
