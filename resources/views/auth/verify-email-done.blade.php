<x-guest-layout>
    {{-- Page d'ARRIVÉE du lien de confirmation, ouverte depuis n'importe quel navigateur. --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10">
                <p class="cx-kicker">{{ __('Vérification e-mail') }}</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">
                    {{ __('Adresse') }}<br><span class="cx-gradient-text">{{ __('confirmée.') }}</span>
                </h1>

                <p class="cx-lede mt-4 text-sm">
                    {{ __('Votre compte est actif. Retournez dans l’application et touchez « J’ai confirmé » — ou reprenez votre navigation ci-dessous.') }}
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('login') }}" class="cx-btn cx-btn--primary w-full px-5 py-4 text-center text-base">
                        {{ __('Se connecter') }} →
                    </a>
                    <a href="{{ url('/') }}" class="cx-btn w-full px-5 py-4 text-center text-base">
                        {{ __('Accueil') }}
                    </a>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
