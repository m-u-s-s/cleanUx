<x-guest-layout>
    <main class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <section class="mx-auto grid max-w-7xl grid-cols-1 gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-16">

            {{-- LEFT MARKETING --}}
            <div class="hidden lg:flex flex-col justify-between rounded-[2rem] bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-10 text-white shadow-2xl">
                <div>
                    <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-blue-100">
                        CleanUx Platform
                    </span>

                    <h1 class="mt-8 text-5xl font-black leading-tight">
                        Créez votre compte et réservez plus simplement.
                    </h1>

                    <p class="mt-5 text-lg leading-8 text-slate-300">
                        Un espace moderne pour gérer vos rendez-vous, suivre les missions,
                        recevoir vos devis et laisser vos feedbacks.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">GPS</p>
                        <p class="mt-1 text-sm text-slate-300">Suivi employé</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">Code</p>
                        <p class="mt-1 text-sm text-slate-300">Début & fin</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">B2B</p>
                        <p class="mt-1 text-sm text-slate-300">Entreprises</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                        <p class="text-2xl font-black">★</p>
                        <p class="mt-1 text-sm text-slate-300">Feedback qualité</p>
                    </div>
                </div>
            </div>

            {{-- FORM --}}
            <div class="flex items-center">
                <div class="w-full rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
                    <div class="mb-6">
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-600">
                            Inscription
                        </p>

                        <h2 class="mt-2 text-3xl font-black text-slate-900">
                            Créer un compte
                        </h2>

                        <p class="mt-2 text-sm text-slate-500">
                            Choisissez le profil qui correspond à votre utilisation de CleanUx.
                        </p>
                    </div>

                    <x-validation-errors class="mb-4" />

                    <form method="POST" action="{{ route('register') }}" class="space-y-5">
                        @csrf

                        {{-- ROLE --}}
                        <div x-data="{ role: '{{ old('role', 'client') }}' }">
                            <label class="text-sm font-bold text-slate-700">Type de compte</label>

                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <label class="cursor-pointer rounded-2xl border p-4 transition"
                                       :class="role === 'client' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="role" value="client" x-model="role" class="sr-only">
                                    <p class="font-black text-slate-900">Client</p>
                                    <p class="mt-1 text-xs text-slate-500">Réserver un nettoyage</p>
                                </label>

                                <label class="cursor-pointer rounded-2xl border p-4 transition"
                                       :class="role === 'entreprise' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="role" value="entreprise" x-model="role" class="sr-only">
                                    <p class="font-black text-slate-900">Entreprise</p>
                                    <p class="mt-1 text-xs text-slate-500">Bureaux / multi-sites</p>
                                </label>

                                <label class="cursor-pointer rounded-2xl border p-4 transition"
                                       :class="role === 'employe' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="role" value="employe" x-model="role" class="sr-only">
                                    <p class="font-black text-slate-900">Employé</p>
                                    <p class="mt-1 text-xs text-slate-500">Accès terrain</p>
                                </label>
                            </div>

                            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <x-label for="name" value="Nom complet" />
                                    <x-input id="name"
                                             class="mt-1 block w-full rounded-xl"
                                             type="text"
                                             name="name"
                                             :value="old('name')"
                                             required
                                             autofocus
                                             autocomplete="name"
                                             placeholder="Ex : Imane Darouich" />
                                </div>

                                <div class="sm:col-span-2">
                                    <x-label for="email" value="Adresse e-mail" />
                                    <x-input id="email"
                                             class="mt-1 block w-full rounded-xl"
                                             type="email"
                                             name="email"
                                             :value="old('email')"
                                             required
                                             autocomplete="username"
                                             placeholder="exemple@email.com" />
                                </div>

                                <div x-show="role === 'entreprise'" x-cloak class="sm:col-span-2 rounded-2xl border border-blue-100 bg-blue-50 p-4">
                                    <x-label for="tva_number" value="Numéro de TVA" />
                                    <x-input id="tva_number"
                                             class="mt-1 block w-full rounded-xl"
                                             type="text"
                                             name="tva_number"
                                             :value="old('tva_number')"
                                             placeholder="Ex : BE0123.456.789" />

                                    <p class="mt-2 text-xs text-blue-700">
                                        Utile pour la facturation B2B, les sites d’entreprise et les validations internes.
                                    </p>
                                </div>

                                <div class="sm:col-span-2" x-show="role === 'employe'" x-cloak>
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                        ⚠️ Les comptes employés doivent être validés par un administrateur avant utilisation.
                                    </div>
                                </div>

                                <div>
                                    <x-label for="password" value="Mot de passe" />
                                    <x-input id="password"
                                             class="mt-1 block w-full rounded-xl"
                                             type="password"
                                             name="password"
                                             required
                                             autocomplete="new-password"
                                             placeholder="••••••••" />
                                </div>

                                <div>
                                    <x-label for="password_confirmation" value="Confirmation" />
                                    <x-input id="password_confirmation"
                                             class="mt-1 block w-full rounded-xl"
                                             type="password"
                                             name="password_confirmation"
                                             required
                                             autocomplete="new-password"
                                             placeholder="••••••••" />
                                </div>
                            </div>
                        </div>

                        @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <x-label for="terms">
                                    <div class="flex items-start gap-3">
                                        <x-checkbox name="terms" id="terms" required class="mt-1" />

                                        <div class="text-sm text-slate-600">
                                            {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                                'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="font-semibold text-blue-600 underline hover:text-blue-800">'.__('Terms of Service').'</a>',
                                                'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="font-semibold text-blue-600 underline hover:text-blue-800">'.__('Privacy Policy').'</a>',
                                            ]) !!}
                                        </div>
                                    </div>
                                </x-label>
                            </div>
                        @endif

                        <button type="submit"
                                class="flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-100 hover:bg-blue-700">
                            Créer mon compte
                        </button>

                        <div class="text-center text-sm text-slate-500">
                            Déjà inscrit ?
                            <a href="{{ route('login') }}" class="font-bold text-blue-600 hover:text-blue-700">
                                Se connecter
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>