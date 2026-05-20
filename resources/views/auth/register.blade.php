<x-guest-layout>
    {{--
      Page REGISTER — vitrine (cx-shell sombre)
      Backend Fortify préservé À L'IDENTIQUE :
        - POST {{ route('register') }} + @csrf
    - hidden `account_type` parmi : client_personal | client_company | provider_independent | provider_company
    - champs name / email / password / password_confirmation
    - conditionnels : company_name + tva_number (client_company) / provider_company_name + tva_number (provider_company)
    - checkbox `terms` si Jetstream::hasTermsAndPrivacyPolicyFeature()
    Logique Alpine.js (x-data) conservée à l'identique.
    Wording corrigé : "Intervenant indépendant" / "Société de services" (multi-métiers).
    --}}

    <main class="relative z-[1] min-h-screen pt-24 pb-16 sm:pt-28 sm:pb-24">
        <section class="mx-auto grid max-w-7xl grid-cols-1 items-start gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">

            {{-- ── PANNEAU MARQUE ─────────────────────────────────────────────── --}}
            <aside class="hidden lg:block lg:sticky lg:top-24" data-cx-reveal>
                <div class="cx-card relative overflow-hidden p-10">
                    <span class="cx-chip"><span class="pip"></span> Rejoindre la plateforme</span>

                    <h1 class="cx-h mt-8 text-5xl lg:text-6xl">
                        Une seule plateforme,<br><span class="cx-gradient-text">quatre profils.</span>
                    </h1>
                    <p class="cx-lede mt-6 max-w-md text-base">
                        Que vous réserviez ou interveniez, en particulier ou en entreprise — votre espace
                        s'adapte à votre usage et à vos métiers.
                    </p>

                    <div class="mt-10 grid grid-cols-2 gap-3">
                        @foreach ([
                        ['Suivi live','Position & ETA en direct'],
                        ['Multi-métiers','Nettoyage, peinture, bâtiment, jardinage'],
                        ['Paiements','Stripe sécurisé'],
                        ['B2B','Multi-sites & factures'],
                        ] as $i => $f)
                        <div class="rounded-2xl border p-5"
                            style="border-color:var(--cx-line);background:rgba(255,255,255,.03)"
                            data-cx-reveal data-cx-delay="{{ $i }}">
                            <p class="text-lg font-extrabold" style="font-family:var(--cx-display);color:var(--cx-text)">{{ $f[0] }}</p>
                            <p class="mt-1 text-xs" style="color:var(--cx-muted)">{{ $f[1] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            {{-- ── FORMULAIRE ────────────────────────────────────────────────── --}}
            <div data-cx-reveal data-cx-delay="1">
                <div class="cx-card p-7 sm:p-10">
                    <p class="cx-kicker">Inscription</p>
                    <h2 class="cx-h mt-3 text-3xl sm:text-4xl">Créer votre <span class="cx-gradient-text">compte.</span></h2>
                    <p class="cx-lede mt-3 text-sm">Choisissez d'abord votre profil — le formulaire s'adapte.</p>

                    <x-validation-errors class="mt-6" />

                    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-6"
                        x-data="{
                              type: '{{ old('account_type', '') }}',
                              isProviderCompany() { return this.type === 'provider_company'; },
                              isClientCompany()   { return this.type === 'client_company'; },
                          }">
                        @csrf

                        {{-- Étape 1 : profil --}}
                        <div>
                            <label class="mb-3 block text-xs font-bold uppercase tracking-[0.14em]" style="color:var(--cx-muted)">
                                Je suis…
                            </label>
                            <input type="hidden" name="account_type" :value="type">

                            <div class="grid grid-cols-2 gap-3">
                                @php
                                $profils = [
                                ['key'=>'client_personal', 'emoji'=>'👤', 'label'=>'Client particulier', 'hint'=>'Je réserve pour mon domicile', 'accent'=>'#4fe3d6'],
                                ['key'=>'client_company', 'emoji'=>'🏢', 'label'=>'Client entreprise', 'hint'=>'Bureaux / multi-sites', 'accent'=>'#8b7bff'],
                                ['key'=>'provider_independent','emoji'=>'🔧', 'label'=>'Intervenant indépendant','hint'=>'Je travaille à mon compte', 'accent'=>'#ffb648'],
                                ['key'=>'provider_company', 'emoji'=>'🏗️', 'label'=>'Société de services', 'hint'=>'Je gère une équipe', 'accent'=>'#5fd38a'],
                                ];
                                @endphp

                                @foreach ($profils as $p)
                                <label class="relative cursor-pointer rounded-2xl border p-4 transition"
                                    :class="type === '{{ $p['key'] }}' ? 'ring-2' : 'hover:bg-white/[0.04]'"
                                    :style="type === '{{ $p['key'] }}'
                                               ? 'border-color:{{ $p['accent'] }}; background:linear-gradient(160deg, color-mix(in srgb, {{ $p['accent'] }} 12%, transparent), rgba(255,255,255,.02)); --tw-ring-color:color-mix(in srgb, {{ $p['accent'] }} 35%, transparent)'
                                               : 'border-color:var(--cx-line); background:rgba(255,255,255,.025)'"
                                    @click="type = '{{ $p['key'] }}'">
                                    <div class="text-2xl">{{ $p['emoji'] }}</div>
                                    <p class="mt-2 text-sm font-extrabold" style="font-family:var(--cx-display);color:var(--cx-text)">
                                        {{ $p['label'] }}
                                    </p>
                                    <p class="mt-1 text-xs leading-snug" style="color:var(--cx-muted)">
                                        {{ $p['hint'] }}
                                    </p>
                                    <div x-show="type === '{{ $p['key'] }}'" x-cloak class="absolute right-3 top-3">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black"
                                            style="background:{{ $p['accent'] }};color:#0b1120">✓</span>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Étape 2 : champs communs (apparaissent après choix du profil) --}}
                        <div class="space-y-5" x-show="type !== ''" x-cloak>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="cx-field sm:col-span-2">
                                    <label for="name">Nom complet</label>
                                    <input id="name" name="name" type="text" required autofocus
                                        placeholder="Jean Dupont" value="{{ old('name') }}">
                                </div>

                                <div class="cx-field sm:col-span-2">
                                    <label for="email">Adresse e-mail</label>
                                    <input id="email" name="email" type="email" required
                                        placeholder="jean@exemple.com" value="{{ old('email') }}">
                                </div>

                                {{-- Conditionnel : société cliente --}}
                                <div class="sm:col-span-2" x-show="isClientCompany()" x-cloak>
                                    <div class="rounded-2xl border p-5"
                                        style="border-color:rgba(139,123,255,.35); background:linear-gradient(160deg, rgba(139,123,255,.10), rgba(255,255,255,.02))">
                                        <div class="cx-field">
                                            <label for="company_name">Nom de votre entreprise</label>
                                            <input id="company_name" name="company_name" type="text"
                                                placeholder="Acme SA" value="{{ old('company_name') }}">
                                        </div>
                                        <div class="cx-field mt-4">
                                            <label for="tva_number">Numéro de TVA</label>
                                            <input id="tva_number" name="tva_number" type="text"
                                                placeholder="BE0123.456.789" value="{{ old('tva_number') }}">
                                        </div>
                                        <p class="mt-3 text-xs" style="color:var(--cx-violet)">
                                            Vous pourrez inviter vos collègues et enregistrer vos sites après l'inscription.
                                        </p>
                                    </div>
                                </div>

                                {{-- Conditionnel : société prestataire --}}
                                <div class="sm:col-span-2" x-show="isProviderCompany()" x-cloak>
                                    <div class="rounded-2xl border p-5"
                                        style="border-color:rgba(95,211,138,.35); background:linear-gradient(160deg, rgba(95,211,138,.10), rgba(255,255,255,.02))">
                                        <div class="cx-field">
                                            <label for="provider_company_name">Nom de votre société de services</label>
                                            <input id="provider_company_name" name="provider_company_name" type="text"
                                                placeholder="ProServices SPRL" value="{{ old('provider_company_name') }}">
                                        </div>
                                        <div class="cx-field mt-4">
                                            <label for="tva_number_provider">Numéro de TVA</label>
                                            <input id="tva_number_provider" name="tva_number" type="text"
                                                placeholder="BE0123.456.789" value="{{ old('tva_number') }}">
                                        </div>
                                        <div class="mt-3 flex items-start gap-2 text-xs" style="color:#5fd38a">
                                            <span>⚠</span>
                                            <span>Votre compte sera vérifié par notre équipe avant d'être activé. Vous pourrez ensuite inviter votre équipe.</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Note prestataire indépendant --}}
                                <div class="sm:col-span-2" x-show="type === 'provider_independent'" x-cloak>
                                    <div class="rounded-2xl border p-4 text-xs"
                                        style="border-color:rgba(255,182,72,.35); background:rgba(255,182,72,.08); color:var(--cx-amber)">
                                        ✓ En tant qu'indépendant, vous serez visible des clients après validation. Pensez à configurer Stripe pour vos paiements.
                                    </div>
                                </div>

                                <div class="cx-field">
                                    <label for="password">Mot de passe</label>
                                    <input id="password" name="password" type="password" required placeholder="••••••••">
                                </div>

                                <div class="cx-field">
                                    <label for="password_confirmation">Confirmation</label>
                                    <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="••••••••">
                                </div>
                            </div>

                            {{-- CGU --}}
                            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                            <label class="flex items-start gap-3 rounded-2xl border p-4"
                                style="border-color:var(--cx-line);background:rgba(255,255,255,.03)">
                                <input type="checkbox" name="terms" id="terms" required
                                    style="accent-color:var(--cx-amber);height:18px;width:18px;margin-top:2px">
                                <span class="text-sm" style="color:var(--cx-muted)">
                                    {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="font-bold underline" style="color:var(--cx-amber)">'.__('Terms of Service').'</a>',
                                    'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="font-bold underline" style="color:var(--cx-amber)">'.__('Privacy Policy').'</a>',
                                    ]) !!}
                                </span>
                            </label>
                            @endif

                            <button type="submit" class="cx-btn cx-btn--primary w-full px-5 py-4 text-base">
                                Créer mon compte →
                            </button>
                        </div>

                        {{-- État initial : aide à choisir --}}
                        <p class="text-center text-xs" style="color:var(--cx-muted)" x-show="type === ''">
                            ↑ Sélectionnez un profil pour continuer
                        </p>

                        <div class="text-center text-sm" style="color:var(--cx-muted)">
                            Déjà inscrit ?
                            <a href="{{ route('login') }}" class="font-bold" style="color:var(--cx-amber)">Se connecter</a>
                        </div>
                    </form>
                </div>
            </div>

        </section>
    </main>
</x-guest-layout>