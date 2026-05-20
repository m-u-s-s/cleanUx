<x-guest-layout>
    {{--
      Page VERIFY-EMAIL — vitrine (cx-shell)
      Backend Fortify préservé :
        - Form 1 : POST route('verification.send') + @csrf  (renvoi du lien)
        - Form 2 : POST route('logout') + @csrf  (déconnexion)
        - Lien   : route('profile.show')
        - Check  : session('status') == 'verification-link-sent'
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8" data-cx-reveal>
            <div class="cx-card p-7 sm:p-10">
                <p class="cx-kicker">Vérification e-mail</p>
                <h1 class="cx-h mt-3 text-3xl sm:text-4xl">Confirmez<br><span class="cx-gradient-text">votre adresse.</span></h1>

                <p class="cx-lede mt-4 text-sm">
                    {{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
                </p>

                @if (session('status') == 'verification-link-sent')
                    <div class="mt-6 rounded-2xl border p-4 text-sm"
                         style="border-color:rgba(79,227,214,.35);background:rgba(79,227,214,.08);color:var(--cx-cyan)">
                        ✓ {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('verification.send') }}" class="mt-8">
                    @csrf
                    <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                        {{ __('Resend Verification Email') }} →
                    </button>
                </form>

                <div class="mt-6 flex flex-col items-center gap-3 border-t pt-6 sm:flex-row sm:justify-between"
                     style="border-color:var(--cx-line)">
                    <a href="{{ route('profile.show') }}"
                       class="text-sm font-bold uppercase tracking-[0.14em]" style="color:var(--cx-muted)">
                        {{ __('Edit Profile') }} →
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-sm font-bold uppercase tracking-[0.14em] underline decoration-dotted"
                                style="color:var(--cx-muted)">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>