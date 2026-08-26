<x-guest-layout>
    {{--
      Page CONFIRM-PASSWORD — vitrine (cx-shell)

      CETTE VUE ETAIT UNE COPIE OCTET POUR OCTET DE `reset-password`. Elle servait donc un
      formulaire de REINITIALISATION : POST vers `password.update`, avec un champ cache
      `token` alimente par `$request->route('token')`. Or `/user/confirm-password` n'a pas
      de segment `{token}` — le jeton partait VIDE et le broker refusait a tous les coups.

      Fortify attend ici tout autre chose : `ConfirmablePasswordController@store` sur
      `password.confirm.store`, un seul champ `password`, celui que l'on possede DEJA. La
      page ne change pas le mot de passe : elle prouve qu'on le connait avant une action
      sensible.
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10">
                <p class="cx-kicker">{{ __('Zone protégée') }}</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">{{ __('Confirmez') }}<br><span class="cx-gradient-text">{{ __('que c’est bien vous.') }}</span></h1>
                <p class="cx-lede mt-4 text-sm">
                    {{ __('Cette action est sensible. Saisissez votre mot de passe actuel pour continuer.') }}
                </p>

                <x-validation-errors class="mt-6" />

                <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-8 space-y-5">
                    @csrf

                    <div class="cx-field">
                        <label for="password">{{ __('Password') }}</label>
                        <input id="password" name="password" type="password" required autofocus
                               autocomplete="current-password" placeholder="••••••••">
                    </div>

                    <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                        {{ __('Confirm') }} →
                    </button>
                </form>
            </div>
        </section>
    </main>
</x-guest-layout>
