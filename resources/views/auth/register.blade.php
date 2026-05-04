<x-guest-layout>
<main class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
<section class="mx-auto grid max-w-7xl grid-cols-1 gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-16">

    {{-- ── Panneau marketing ─────────────────────────────────────── --}}
    <div class="hidden lg:flex flex-col justify-between rounded-[2rem] bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-10 text-white shadow-2xl">
        <div>
            <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-blue-100">
                CleanUx Platform
            </span>
            <h1 class="mt-8 text-5xl font-black leading-tight">
                La plateforme professionnelle du nettoyage en Belgique.
            </h1>
            <p class="mt-5 text-lg leading-8 text-slate-300">
                Réservations, missions terrain, paiements automatiques, communication d'équipe. Tout en un.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                <p class="text-2xl font-black">GPS</p>
                <p class="mt-1 text-sm text-slate-300">Suivi temps réel</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                <p class="text-2xl font-black">B2B</p>
                <p class="mt-1 text-sm text-slate-300">Multi-sites & équipes</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                <p class="text-2xl font-black">💬</p>
                <p class="mt-1 text-sm text-slate-300">Chat d'équipe</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                <p class="text-2xl font-black">🤖</p>
                <p class="mt-1 text-sm text-slate-300">Assistant IA</p>
            </div>
        </div>
    </div>

    {{-- ── Formulaire ─────────────────────────────────────────────── --}}
    <div class="flex items-center">
    <div class="w-full rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl sm:p-8">

        <div class="mb-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-600">Inscription</p>
            <h2 class="mt-2 text-3xl font-black text-slate-900">Créer un compte</h2>
            <p class="mt-2 text-sm text-slate-500">Choisissez votre profil — 4 types disponibles.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form
            method="POST"
            action="{{ route('register') }}"
            class="space-y-5"
            x-data="{
                type: '{{ old('account_type', '') }}',
                isClient() { return this.type === 'client_personal' || this.type === 'client_company'; },
                isProvider() { return this.type === 'provider_independent' || this.type === 'provider_company'; },
                isCompany() { return this.type === 'client_company' || this.type === 'provider_company'; },
                isProviderCompany() { return this.type === 'provider_company'; },
                isClientCompany() { return this.type === 'client_company'; },
            }"
        >
            @csrf

            {{-- ── Étape 1 : Choisir son profil ── --}}
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">
                    Je suis…
                </label>
                <input type="hidden" name="account_type" :value="type">

                <div class="grid grid-cols-2 gap-3">

                    {{-- Client particulier --}}
                    <label
                        :class="type === 'client_personal'
                            ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-200 shadow-md'
                            : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                        class="relative cursor-pointer rounded-2xl border p-4 transition-all"
                        @click="type = 'client_personal'"
                    >
                        <div class="text-2xl mb-2">👤</div>
                        <p class="font-black text-slate-900 text-sm">Client particulier</p>
                        <p class="mt-1 text-xs text-slate-500 leading-snug">Je réserve pour mon domicile</p>
                        <div x-show="type === 'client_personal'" class="absolute top-2 right-2">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600">
                                <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                </svg>
                            </div>
                        </div>
                    </label>

                    {{-- Client entreprise --}}
                    <label
                        :class="type === 'client_company'
                            ? 'border-purple-600 bg-purple-50 ring-2 ring-purple-200 shadow-md'
                            : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                        class="relative cursor-pointer rounded-2xl border p-4 transition-all"
                        @click="type = 'client_company'"
                    >
                        <div class="text-2xl mb-2">🏢</div>
                        <p class="font-black text-slate-900 text-sm">Client entreprise</p>
                        <p class="mt-1 text-xs text-slate-500 leading-snug">Bureaux / multi-sites</p>
                        <div x-show="type === 'client_company'" class="absolute top-2 right-2">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-purple-600">
                                <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                </svg>
                            </div>
                        </div>
                    </label>

                    {{-- Nettoyeur indépendant --}}
                    <label
                        :class="type === 'provider_independent'
                            ? 'border-green-600 bg-green-50 ring-2 ring-green-200 shadow-md'
                            : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                        class="relative cursor-pointer rounded-2xl border p-4 transition-all"
                        @click="type = 'provider_independent'"
                    >
                        <div class="text-2xl mb-2">🧹</div>
                        <p class="font-black text-slate-900 text-sm">Nettoyeur indépendant</p>
                        <p class="mt-1 text-xs text-slate-500 leading-snug">Je travaille à mon compte</p>
                        <div x-show="type === 'provider_independent'" class="absolute top-2 right-2">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-green-600">
                                <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                </svg>
                            </div>
                        </div>
                    </label>

                    {{-- Société de nettoyage --}}
                    <label
                        :class="type === 'provider_company'
                            ? 'border-amber-600 bg-amber-50 ring-2 ring-amber-200 shadow-md'
                            : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                        class="relative cursor-pointer rounded-2xl border p-4 transition-all"
                        @click="type = 'provider_company'"
                    >
                        <div class="text-2xl mb-2">🏗️</div>
                        <p class="font-black text-slate-900 text-sm">Société de nettoyage</p>
                        <p class="mt-1 text-xs text-slate-500 leading-snug">Je gère une équipe</p>
                        <div x-show="type === 'provider_company'" class="absolute top-2 right-2">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-600">
                                <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                </svg>
                            </div>
                        </div>
                    </label>

                </div>
            </div>

            {{-- ── Champs communs ── --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-show="type !== ''">

                <div class="sm:col-span-2">
                    <x-label for="name" value="Nom complet" />
                    <x-input id="name" class="mt-1 block w-full rounded-xl"
                        type="text" name="name" :value="old('name')"
                        required autofocus placeholder="Jean Dupont" />
                </div>

                <div class="sm:col-span-2">
                    <x-label for="email" value="Adresse e-mail" />
                    <x-input id="email" class="mt-1 block w-full rounded-xl"
                        type="email" name="email" :value="old('email')"
                        required placeholder="jean@exemple.com" />
                </div>

                {{-- Nom de la société cliente --}}
                <div class="sm:col-span-2" x-show="isClientCompany()" x-cloak>
                    <div class="rounded-2xl border border-purple-100 bg-purple-50 p-4">
                        <x-label for="company_name" value="Nom de votre entreprise" />
                        <x-input id="company_name" class="mt-1 block w-full rounded-xl"
                            type="text" name="company_name" :value="old('company_name')"
                            placeholder="Acme SA" />
                        <x-label for="tva_number" value="Numéro de TVA" class="mt-3" />
                        <x-input id="tva_number" class="mt-1 block w-full rounded-xl"
                            type="text" name="tva_number" :value="old('tva_number')"
                            placeholder="BE0123.456.789" />
                        <p class="mt-2 text-xs text-purple-600">Vous pourrez inviter vos collègues et enregistrer vos locaux après l'inscription.</p>
                    </div>
                </div>

                {{-- Nom de la société de nettoyage --}}
                <div class="sm:col-span-2" x-show="isProviderCompany()" x-cloak>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                        <x-label for="provider_company_name" value="Nom de votre société de nettoyage" />
                        <x-input id="provider_company_name" class="mt-1 block w-full rounded-xl"
                            type="text" name="provider_company_name" :value="old('provider_company_name')"
                            placeholder="CleanPro SPRL" />
                        <x-label for="tva_number_provider" value="Numéro de TVA" class="mt-3" />
                        <x-input id="tva_number_provider" class="mt-1 block w-full rounded-xl"
                            type="text" name="tva_number" :value="old('tva_number')"
                            placeholder="BE0123.456.789" />
                        <div class="mt-2 flex items-start gap-2 text-xs text-amber-700">
                            <span>⚠️</span>
                            <span>Votre compte sera vérifié par notre équipe avant d'être activé. Vous pourrez ensuite inviter votre équipe.</span>
                        </div>
                    </div>
                </div>

                {{-- Alerte prestataire indépendant --}}
                <div class="sm:col-span-2" x-show="type === 'provider_independent'" x-cloak>
                    <div class="rounded-2xl border border-green-100 bg-green-50 p-3 text-xs text-green-700">
                        ✓ En tant qu'indépendant, vous serez visible des clients dès validation de votre profil. Pensez à configurer votre compte Stripe pour recevoir vos paiements.
                    </div>
                </div>

                <div>
                    <x-label for="password" value="Mot de passe" />
                    <x-input id="password" class="mt-1 block w-full rounded-xl"
                        type="password" name="password" required placeholder="••••••••" />
                </div>

                <div>
                    <x-label for="password_confirmation" value="Confirmation" />
                    <x-input id="password_confirmation" class="mt-1 block w-full rounded-xl"
                        type="password" name="password_confirmation" required placeholder="••••••••" />
                </div>

            </div>

            {{-- CGU --}}
            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="rounded-2xl bg-slate-50 p-4" x-show="type !== ''" x-cloak>
                    <x-label for="terms">
                        <div class="flex items-start gap-3">
                            <x-checkbox name="terms" id="terms" required class="mt-1" />
                            <div class="text-sm text-slate-600">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="font-semibold text-blue-600 underline">'.__('Terms of Service').'</a>',
                                    'privacy_policy'   => '<a target="_blank" href="'.route('policy.show').'" class="font-semibold text-blue-600 underline">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <button
                type="submit"
                x-show="type !== ''"
                x-cloak
                :class="{
                    'bg-blue-600 shadow-blue-100 hover:bg-blue-700':   type === 'client_personal',
                    'bg-purple-600 shadow-purple-100 hover:bg-purple-700': type === 'client_company',
                    'bg-green-600 shadow-green-100 hover:bg-green-700':  type === 'provider_independent',
                    'bg-amber-600 shadow-amber-100 hover:bg-amber-700':  type === 'provider_company',
                }"
                class="flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black text-white shadow-lg transition"
            >
                Créer mon compte →
            </button>

            <div class="text-center text-sm text-slate-500">
                Déjà inscrit ?
                <a href="{{ route('login') }}" class="font-bold text-blue-600 hover:text-blue-700">Se connecter</a>
            </div>

        </form>
    </div>
    </div>

</section>
</main>
</x-guest-layout>
