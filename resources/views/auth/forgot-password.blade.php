<x-guest-layout>
    {{--
      Page FORGOT-PASSWORD — vitrine (cx-shell)
      Backend Fortify préservé : POST route('password.email') + @csrf + champ email
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10">
                <p class="cx-kicker">Mot de passe oublié</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">On vous renvoie<br><span class="cx-gradient-text">un lien.</span></h1>
                <p class="cx-lede mt-4 text-sm">
                    {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
                </p>

                @if (session('status'))
                    <div class="mt-6 rounded-2xl border p-4 text-sm"
                         style="border-color:rgba(79,227,214,.35);background:rgba(79,227,214,.08);color:var(--cx-cyan)">
                        {{ session('status') }}
                    </div>
                @endif

                <x-validation-errors class="mt-6" />

                <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
                    @csrf

                    <div class="cx-field">
                        <label for="email">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" required autofocus
                               autocomplete="username" placeholder="exemple@email.com"
                               value="{{ old('email') }}">
                    </div>

                    <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                        {{ __('Email Password Reset Link') }} →
                    </button>
                </form>

                <p class="mt-6 text-center text-sm" style="color:var(--cx-muted)">
                    <a href="{{ route('login') }}" class="font-bold" style="color:var(--cx-amber)">← Retour à la connexion</a>
                </p>
            </div>
        </section>
    </main>
</x-guest-layout>