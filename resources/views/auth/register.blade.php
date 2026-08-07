<x-guest-layout>
    {{-- ============================================================
         REGISTER — refonte Stripe-style (21/05/2026)
         Backend Fortify préservé À L'IDENTIQUE :
           POST route('register') + @csrf
           hidden account_type ∈ {client_personal | client_company | provider_independent | provider_company}
           champs name / email / password / password_confirmation
           conditionnels : company_name + tva_number selon profil
           checkbox terms si Jetstream::hasTermsAndPrivacyPolicyFeature()
         ============================================================ --}}

    <main class="min-h-screen bg-slate-50/40 px-4 py-12">
        <div class="mx-auto max-w-2xl">
            {{-- Brand --}}
            <div class="text-center mb-6">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-slate-700 hover:text-brand-600 transition">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white font-bold text-lg shadow-soft-sm">
                        Br
                    </span>
                    <span class="text-lg font-bold">{{ config('app.name', 'Brio') }}</span>
                </a>
            </div>

            <div class="cu-glass rounded-2xl border border-slate-200/80 shadow-soft p-6 sm:p-10">
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Créer votre compte</h1>
                    <p class="mt-2 text-sm text-slate-500">Choisissez d'abord votre profil — le formulaire s'adapte</p>
                </div>

                <x-validation-errors class="mb-6" />

                <form method="POST" action="{{ route('register') }}" class="space-y-6"
                      x-data="{
                          type: '{{ old('account_type', '') }}',
                          isProviderCompany() { return this.type === 'provider_company'; },
                          isClientCompany()   { return this.type === 'client_company'; },
                      }">
                    @csrf

                    {{-- Étape 1 : choix profil --}}
                    <div>
                        <label class="ui-label">Je suis…</label>
                        <input type="hidden" name="account_type" :value="type">

                        @php
                        $profils = [
                            ['key' => 'client_personal',      'icon' => 'user',            'label' => 'Client particulier',  'hint' => 'Je réserve pour mon domicile'],
                            ['key' => 'client_company',       'icon' => 'building-office', 'label' => 'Client entreprise',   'hint' => 'Bureaux / multi-sites'],
                            ['key' => 'provider_independent', 'icon' => 'wrench',          'label' => 'Prestataire indé',    'hint' => 'Je travaille à mon compte'],
                            ['key' => 'provider_company',     'icon' => 'briefcase',       'label' => 'Société de services', 'hint' => 'Je gère une équipe'],
                        ];
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($profils as $p)
                                <label class="relative cursor-pointer rounded-xl border-2 p-4 transition"
                                       :class="type === '{{ $p['key'] }}' ? 'border-brand-500 bg-brand-50/50' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                                       @click="type = '{{ $p['key'] }}'">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-10 place-items-center rounded-lg bg-white ring-1 ring-slate-200">
                                            <x-ui.icon :name="$p['icon']" class="w-5 h-5 text-slate-600" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">{{ $p['label'] }}</p>
                                            <p class="text-xs text-slate-500">{{ $p['hint'] }}</p>
                                        </div>
                                        <div x-show="type === '{{ $p['key'] }}'" x-cloak class="ml-auto">
                                            <div class="grid h-5 w-5 place-items-center rounded-full bg-brand-600 text-white">
                                                <x-ui.icon name="check" class="w-3 h-3" stroke="3" />
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Étape 2 : champs --}}
                    <div class="space-y-4" x-show="type !== ''" x-cloak>
                        <div>
                            <label for="name" class="ui-label">Nom complet</label>
                            <input id="name" name="name" type="text" required autofocus
                                   placeholder="Jean Dupont" value="{{ old('name') }}"
                                   class="ui-input">
                        </div>

                        <div>
                            <label for="email" class="ui-label">Adresse e-mail</label>
                            <input id="email" name="email" type="email" required
                                   placeholder="jean@exemple.com" value="{{ old('email') }}"
                                   class="ui-input">
                        </div>

                        {{-- Conditionnel : société cliente --}}
                        <div x-show="isClientCompany()" x-cloak>
                            <div class="rounded-xl border border-purple-200 bg-purple-50/30 p-4 space-y-3">
                                <div>
                                    <label for="company_name" class="ui-label">Nom de votre entreprise</label>
                                    <input id="company_name" name="company_name" type="text"
                                           placeholder="Acme SA" value="{{ old('company_name') }}"
                                           class="ui-input">
                                </div>
                                <div>
                                    <label for="tva_number" class="ui-label">Numéro de TVA</label>
                                    <input id="tva_number" name="tva_number" type="text"
                                           placeholder="BE0123.456.789" value="{{ old('tva_number') }}"
                                           class="ui-input">
                                </div>
                                <p class="text-xs text-purple-700">
                                    Vous pourrez inviter vos collègues et enregistrer vos sites après l'inscription.
                                </p>
                            </div>
                        </div>

                        {{-- Conditionnel : société prestataire --}}
                        <div x-show="isProviderCompany()" x-cloak>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/30 p-4 space-y-3">
                                <div>
                                    <label for="provider_company_name" class="ui-label">Nom de votre société de services</label>
                                    <input id="provider_company_name" name="provider_company_name" type="text"
                                           placeholder="ProServices SPRL" value="{{ old('provider_company_name') }}"
                                           class="ui-input">
                                </div>
                                <div>
                                    <label for="tva_number_provider" class="ui-label">Numéro de TVA</label>
                                    <input id="tva_number_provider" name="tva_number" type="text"
                                           placeholder="BE0123.456.789" value="{{ old('tva_number') }}"
                                           class="ui-input">
                                </div>
                                <p class="text-xs text-emerald-700 inline-flex items-start gap-2">
                                    <x-ui.icon name="shield-check" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                                    Votre compte sera vérifié par notre équipe avant d'être activé.
                                </p>
                            </div>
                        </div>

                        {{-- Note prestataire indépendant --}}
                        <div x-show="type === 'provider_independent'" x-cloak>
                            <div class="rounded-xl border border-amber-200 bg-amber-50/30 p-4 text-xs text-amber-700 inline-flex items-start gap-2">
                                <x-ui.icon name="information-circle" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                                En tant qu'indépendant, vous serez visible des clients après validation KYC. Pensez à configurer Stripe Connect pour vos paiements.
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="ui-label">Mot de passe</label>
                                <input id="password" name="password" type="password" required placeholder="••••••••" class="ui-input">
                            </div>
                            <div>
                                <label for="password_confirmation" class="ui-label">Confirmation</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="••••••••" class="ui-input">
                            </div>
                        </div>

                        {{-- CGU --}}
                        @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                                <input type="checkbox" name="terms" id="terms" required
                                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-xs text-slate-600">
                                    {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="' . route('terms.show') . '" class="font-semibold text-brand-700 hover:text-brand-800 underline">' . __('Terms of Service') . '</a>',
                                        'privacy_policy' => '<a target="_blank" href="' . route('policy.show') . '" class="font-semibold text-brand-700 hover:text-brand-800 underline">' . __('Privacy Policy') . '</a>',
                                    ]) !!}
                                </span>
                            </label>
                        @endif

                        <x-ui.button type="submit" variant="amber" size="lg" class="w-full" iconPosition="right" icon="arrow-right">
                            Créer mon compte
                        </x-ui.button>
                    </div>

                    {{-- État initial --}}
                    <p class="text-center text-xs text-slate-500" x-show="type === ''">
                        ↑ Sélectionnez un profil pour continuer
                    </p>
                </form>

                <div class="mt-6 text-center text-sm text-slate-600">
                    Déjà inscrit ?
                    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">Se connecter</a>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-xs text-slate-500 inline-flex items-center gap-2 justify-center">
                    <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-emerald-500" />
                    KYC validé · RGPD compliant · Données chiffrées
                </p>
            </div>
        </div>
    </main>
</x-guest-layout>
