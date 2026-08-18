{{--
    LE RÉCAPITULATIF DE LOCATION.

    Trois pièces, trois questions que le client se pose avant de s'engager :

      LES DEUX PRIX  « la garantie vaut-elle son supplément ? » — la réponse n'existe qu'en montrant
                     les deux totaux ET les deux cautions côte à côte
      L'ADRESSE      « où vais-je chercher la voiture ? » — copiée sur la réservation, pour qu'un
                     déménagement d'agence ne réécrive pas une promesse déjà faite
      LE 360°        « à quoi ressemble-t-elle vraiment ? » — rotation photo ou modèle 3D, selon ce
                     que l'administrateur a déposé pour CETTE voiture

    CE N'EST PAS `order-confirmation.blade.php`. Cette page-là gère un panier multi-articles, des
    prestataires et des acomptes ; rien de cela n'existe pour une location, et rien n'y a été touché.
--}}
<div class="py-8">
    <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">

        @if ($confirmee)
            <div class="rounded-3xl border border-emerald-300 bg-emerald-50 p-6 dark:border-emerald-700 dark:bg-emerald-950/30">
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">Réservation confirmée</p>
                <h1 class="mt-1 text-2xl font-black text-emerald-900 dark:text-emerald-100">
                    Votre référence&nbsp;: {{ $location->reference }}
                </h1>
                <p class="mt-2 text-sm text-emerald-800 dark:text-emerald-200">
                    Présentez cette référence et votre permis au comptoir. Le règlement se fait à l’agence,
                    au moment du retrait.
                </p>
            </div>
        @else
            <header class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Nos locations</p>
                <h1 class="text-3xl font-black text-slate-900 dark:text-white">Vérifiez votre location</h1>
            </header>
        @endif

        @if ($erreur)
            <p class="rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $erreur }}</p>
        @endif

        <div class="grid gap-8 lg:grid-cols-2">

            {{-- ── La voiture, sous l'angle choisi par l'administrateur ────────────── --}}
            <section class="space-y-4">
                @if ($vehicule->modele3d->isNotEmpty())
                    @push('scripts')
                        @vite(['resources/js/rental-3d.js'])
                    @endpush

                    @include('livewire.rental.partials.modele-3d', [
                        'modele' => $vehicule->modele3d->first(),
                        'poster' => $vehicule->vignette(),
                        'alt' => $vehicule->nomComplet(),
                    ])
                @elseif ($vehicule->rotation360->isNotEmpty())
                    @include('livewire.rental.partials.spin-360', [
                        'images' => $vehicule->rotation360,
                        'alt' => $vehicule->nomComplet(),
                    ])
                @elseif ($vehicule->galerie->isNotEmpty())
                    <img src="{{ $vehicule->galerie->first()->url() }}" alt="{{ $vehicule->nomComplet() }}"
                         class="aspect-[4/3] w-full rounded-3xl object-cover">
                @endif

                <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ $vehicule->nomComplet() }}</h2>
                    <dl class="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between"><dt>Départ</dt><dd class="font-semibold">{{ $location->starts_at?->format('d/m/Y à H:i') }}</dd></div>
                        <div class="flex justify-between"><dt>Retour</dt><dd class="font-semibold">{{ $location->ends_at?->format('d/m/Y à H:i') }}</dd></div>
                        <div class="flex justify-between"><dt>Durée facturée</dt><dd class="font-semibold">{{ $location->days }} jour(s)</dd></div>
                        <div class="flex justify-between"><dt>Conducteur</dt><dd class="font-semibold">{{ $location->nomDuConducteur() }}</dd></div>
                    </dl>
                </div>

                {{-- ── L'ADRESSE DE RETRAIT ────────────────────────────────────────── --}}
                <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                    <h3 class="text-sm font-black uppercase tracking-wide text-slate-900 dark:text-white">Où retirer le véhicule</h3>

                    @if ($location->pickup_address)
                        <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $location->pickup_label }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $location->pickup_address }}</p>

                        @if ($vehicule->pickupPoint?->instructions)
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $vehicule->pickupPoint->instructions }}</p>
                        @endif

                        @if ($location->pickup_lat && $location->pickup_lng)
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $location->pickup_lat }},{{ $location->pickup_lng }}"
                               target="_blank" rel="noopener noreferrer"
                               class="mt-3 inline-flex min-h-[44px] items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">
                                Ouvrir l’itinéraire
                            </a>
                        @endif
                    @else
                        {{-- Une agence non renseignée se dit, plutôt que de laisser un blanc que le
                             client interpréterait comme une livraison à domicile. --}}
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                            L’adresse de retrait vous sera communiquée par l’agence.
                        </p>
                    @endif
                </div>
            </section>

            {{-- ── LES DEUX PRIX ──────────────────────────────────────────────────── --}}
            <section class="space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-black uppercase tracking-wide text-slate-900 dark:text-white">Votre formule</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Les deux options, avec leur caution&nbsp;: c’est la caution qui fait la différence, pas
                        seulement le prix.
                    </p>

                    <div class="mt-4 space-y-3">
                        <button type="button"
                            @if (! $confirmee) wire:click="choisirLaProtection('{{ \App\Models\RentalVehicle::PROTECTION_SANS }}')" @endif
                            @class([
                                'w-full rounded-2xl border p-4 text-left transition',
                                'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30' => ! $location->estAvecGarantie(),
                                'border-slate-200 dark:border-slate-700' => $location->estAvecGarantie(),
                                'cursor-default' => $confirmee,
                            ])>
                            <span class="flex items-baseline justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">Sans garantie</span>
                                <span class="text-xl font-black text-slate-900 dark:text-white">
                                    {{ number_format($devis['sans_garantie']['total_cents'] / 100, 2, ',', ' ') }} {{ $devis['currency'] }}
                                </span>
                            </span>
                            <span class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                                Caution à bloquer&nbsp;: <strong>{{ number_format($devis['sans_garantie']['deposit_cents'] / 100, 0, ',', ' ') }} {{ $devis['currency'] }}</strong>
                            </span>
                        </button>

                        @if ($devis['propose_une_garantie'])
                            <button type="button"
                                @if (! $confirmee) wire:click="choisirLaProtection('{{ \App\Models\RentalVehicle::PROTECTION_AVEC }}')" @endif
                                @class([
                                    'w-full rounded-2xl border p-4 text-left transition',
                                    'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30' => $location->estAvecGarantie(),
                                    'border-slate-200 dark:border-slate-700' => ! $location->estAvecGarantie(),
                                    'cursor-default' => $confirmee,
                                ])>
                                <span class="flex items-baseline justify-between">
                                    <span class="font-bold text-slate-900 dark:text-white">Avec garantie</span>
                                    <span class="text-xl font-black text-slate-900 dark:text-white">
                                        {{ number_format($devis['avec_garantie']['total_cents'] / 100, 2, ',', ' ') }} {{ $devis['currency'] }}
                                    </span>
                                </span>
                                <span class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                                    Caution réduite à <strong>{{ number_format($devis['avec_garantie']['deposit_cents'] / 100, 0, ',', ' ') }} {{ $devis['currency'] }}</strong>
                                    · supplément {{ number_format($devis['avec_garantie']['supplement_cents'] / 100, 2, ',', ' ') }} {{ $devis['currency'] }}
                                </span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">À régler à l’agence</span>
                            <span class="text-2xl font-black text-slate-900 dark:text-white">
                                {{ number_format($location->total_cents / 100, 2, ',', ' ') }} {{ $location->currency }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Caution bloquée sur votre carte au retrait&nbsp;:
                            {{ number_format($location->deposit_cents / 100, 0, ',', ' ') }} {{ $location->currency }}
                        </p>
                    </div>

                    @unless ($confirmee)
                        <button type="button" wire:click="confirmer"
                            class="mt-5 min-h-[48px] w-full rounded-2xl bg-slate-900 px-6 text-base font-black text-white transition hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                            Confirmer ma réservation
                        </button>
                        <p class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400">
                            Sans paiement en ligne&nbsp;: vous réglez au comptoir.
                        </p>
                    @endunless
                </div>
            </section>
        </div>
    </div>
</div>
