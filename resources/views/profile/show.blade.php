<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-bold text-2xl text-slate-900 leading-tight">
                Mon compte
            </h2>
            <p class="text-sm text-slate-500">
                Gérez vos informations personnelles, votre sécurité et vos sessions actives.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-1">
                    <div class="bg-white rounded-2xl shadow border p-6 sticky top-24">
                        <h3 class="text-lg font-semibold text-slate-900">Espace compte</h3>
                        <p class="text-sm text-slate-500 mt-1">
                            Retrouvez ici toutes les options liées à votre compte et à votre sécurité.
                        </p>

                        <div class="mt-6 space-y-3 text-sm">
                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="font-medium text-slate-800">Profil</p>
                                <p class="text-slate-500 mt-1">Nom, email, photo</p>
                            </div>

                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="font-medium text-slate-800">Mot de passe</p>
                                <p class="text-slate-500 mt-1">Renforcez la sécurité de votre compte</p>
                            </div>

                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="font-medium text-slate-800">Double authentification</p>
                                <p class="text-slate-500 mt-1">Protection supplémentaire</p>
                            </div>

                            <div class="rounded-xl bg-slate-50 border p-3">
                                <p class="font-medium text-slate-800">Sessions actives</p>
                                <p class="text-slate-500 mt-1">Déconnectez vos autres appareils</p>
                            </div>

                            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                                <div class="rounded-xl bg-red-50 border border-red-200 p-3">
                                    <p class="font-medium text-red-700">Zone sensible</p>
                                    <p class="text-red-500 mt-1">Suppression définitive du compte</p>
                                </div>
                            @endif
                        </div>

                        @if(auth()->user()->role === 'client')
                            <div class="mt-6 pt-6 border-t">
                                <a
                                    href="{{ route('client.profile') }}"
                                    class="text-sm text-blue-600 underline"
                                >
                                    ← Retour au profil client
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="xl:col-span-2 space-y-6">
                    @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                        <div class="bg-white rounded-2xl shadow border p-6">
                            @livewire('profile.update-profile-information-form')
                        </div>
                    @endif

                    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                        <div class="bg-white rounded-2xl shadow border p-6">
                            @livewire('profile.update-password-form')
                        </div>
                    @endif

                    @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                        <div class="bg-white rounded-2xl shadow border p-6">
                            @livewire('profile.two-factor-authentication-form')
                        </div>
                    @endif

                    <div class="bg-white rounded-2xl shadow border p-6">
                        @livewire('profile.logout-other-browser-sessions-form')
                    </div>

                    @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                        <div class="bg-white rounded-2xl shadow border border-red-100 p-6">
                            @livewire('profile.delete-user-form')
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>