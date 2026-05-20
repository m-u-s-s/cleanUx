<x-guest-layout>
    {{--
      Page TWO-FACTOR-CHALLENGE — vitrine (cx-shell)
      Backend Fortify préservé :
        POST route('two-factor.login') + @csrf
        Champs : `code` (TOTP) OU `recovery_code` (toggle Alpine)
        x-refs préservés pour la focus logic au switch.
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10" x-data="{ recovery: false }">
                <p class="cx-kicker">Vérification en deux étapes</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">Confirmez<br><span class="cx-gradient-text">votre identité.</span></h1>

                <p class="cx-lede mt-4 text-sm" x-show="!recovery">
                    {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
                </p>
                <p class="cx-lede mt-4 text-sm" x-cloak x-show="recovery">
                    {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
                </p>

                <x-validation-errors class="mt-6" />

                <form method="POST" action="{{ route('two-factor.login') }}" class="mt-8 space-y-5">
                    @csrf

                    <div class="cx-field" x-show="!recovery">
                        <label for="code">{{ __('Code') }}</label>
                        <input id="code" name="code" type="text" inputmode="numeric"
                               autofocus autocomplete="one-time-code"
                               placeholder="123 456"
                               style="letter-spacing:0.5em; text-align:center; font-family:var(--cx-display); font-size:1.25rem"
                               x-ref="code">
                    </div>

                    <div class="cx-field" x-cloak x-show="recovery">
                        <label for="recovery_code">{{ __('Recovery Code') }}</label>
                        <input id="recovery_code" name="recovery_code" type="text"
                               autocomplete="one-time-code"
                               placeholder="xxxx-xxxx-xxxx"
                               x-ref="recovery_code">
                    </div>

                    <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                        {{ __('Log in') }} →
                    </button>

                    <div class="text-center">
                        <button type="button"
                                class="text-sm font-bold uppercase tracking-[0.14em] underline decoration-dotted"
                                style="color:var(--cx-muted)"
                                x-show="!recovery"
                                x-on:click="recovery = true; $nextTick(() => { $refs.recovery_code.focus() })">
                            {{ __('Use a recovery code') }}
                        </button>
                        <button type="button"
                                class="text-sm font-bold uppercase tracking-[0.14em] underline decoration-dotted"
                                style="color:var(--cx-muted)"
                                x-cloak x-show="recovery"
                                x-on:click="recovery = false; $nextTick(() => { $refs.code.focus() })">
                            {{ __('Use an authentication code') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</x-guest-layout>