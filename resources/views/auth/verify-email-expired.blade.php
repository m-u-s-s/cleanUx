<x-guest-layout>
    {{--
      Un lien de plus de soixante minutes est le cas COURANT : cette page le dit plutôt que de
      rendre un « Forbidden » qui laisse croire à une faute.
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10">
                <p class="cx-kicker">{{ __('Vérification e-mail') }}</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">
                    {{ __('Ce lien') }}<br><span class="cx-gradient-text">{{ __('a expiré.') }}</span>
                </h1>

                <p class="cx-lede mt-4 text-sm">
                    {{ __('Les liens de confirmation ne valent qu’une heure. Demandez-en un nouveau depuis l’application, ou connectez-vous ici pour le renvoyer.') }}
                </p>

                <a href="{{ route('login') }}" class="cx-btn cx-btn--primary mt-8 block w-full px-5 py-4 text-center text-base">
                    {{ __('Se connecter') }} →
                </a>
            </div>
        </section>
    </main>
</x-guest-layout>
