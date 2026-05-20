<x-guest-layout>
    {{--
      Page RESET-PASSWORD — vitrine (cx-shell)
      Backend Fortify préservé :
        POST route('password.update') + @csrf
        hidden `token` ← $request->route('token')
        champs email / password / password_confirmation
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10">
                <p class="cx-kicker">Nouveau mot de passe</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">Choisissez<br><span class="cx-gradient-text">votre nouveau code.</span></h1>
                <p class="cx-lede mt-4 text-sm">
                    Saisissez votre adresse e-mail et le nouveau mot de passe que vous voulez utiliser.
                </p>

                <x-validation-errors class="mt-6" />

                <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
                    @csrf

                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <div class="cx-field">
                        <label for="email">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" required autofocus
                               autocomplete="username" placeholder="exemple@email.com"
                               value="{{ old('email', $request->email) }}">
                    </div>

                    <div class="cx-field">
                        <label for="password">{{ __('Password') }}</label>
                        <input id="password" name="password" type="password" required
                               autocomplete="new-password" placeholder="••••••••">
                    </div>

                    <div class="cx-field">
                        <label for="password_confirmation">{{ __('Confirm Password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               autocomplete="new-password" placeholder="••••••••">
                    </div>

                    <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                        {{ __('Reset Password') }} →
                    </button>
                </form>
            </div>
        </section>
    </main>
</x-guest-layout>