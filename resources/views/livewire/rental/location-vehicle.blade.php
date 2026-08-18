{{--
    LA FICHE D'UNE VOITURE ET SON FORMULAIRE.

    LES QUESTIONS SONT CELLES D'UN COMPTOIR : dates, conducteur, permis, garantie. Rien de plus —
    chaque champ supplémentaire est un client qui renonce, et aucun de ceux-ci n'est décoratif.

    LE PRIX SE RECALCULE À CHAQUE CHANGEMENT DE DATE, avant toute identité. Comme dans le parcours
    de commande, on ne demande pas de compte pour voir un prix.
--}}
<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">

        <a href="{{ route('location.catalogue') }}" wire:navigate
           class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300">
            &larr; Tous nos véhicules
        </a>

        <div class="grid gap-8 lg:grid-cols-2">

            {{-- ── La voiture, vue sous l'angle que l'administrateur a choisi ──────── --}}
            <div class="space-y-4">
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
                @else
                    <div class="flex aspect-[4/3] w-full items-center justify-center rounded-3xl bg-slate-100 text-6xl dark:bg-slate-800" aria-hidden="true">🚗</div>
                @endif

                @if ($vehicule->galerie->count() > 1)
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($vehicule->galerie->skip(1)->take(4) as $photo)
                            <img src="{{ $photo->url() }}" alt="" loading="lazy"
                                 class="aspect-square w-full rounded-xl object-cover">
                        @endforeach
                    </div>
                @endif

                <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ $vehicule->nomComplet() }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $vehicule->category }} · {{ $vehicule->transmission }} · {{ $vehicule->fuel }}
                        @if ($vehicule->year) · {{ $vehicule->year }} @endif
                    </p>

                    @if ($vehicule->description)
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $vehicule->description }}</p>
                    @endif

                    <ul class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->seats }} places</li>
                        <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->doors }} portes</li>
                        <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->luggage }} bagages</li>
                        @if ($vehicule->included_km_per_day)
                            <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $vehicule->included_km_per_day }} km/jour inclus</li>
                        @endif
                        @foreach (($vehicule->features ?? []) as $atout)
                            <li class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $atout }}</li>
                        @endforeach
                    </ul>

                    @if ($vehicule->pickupPoint)
                        <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">
                            <span class="font-semibold">Retrait&nbsp;:</span>
                            {{ $vehicule->pickupPoint->name }} — {{ $vehicule->pickupPoint->adresseComplete() }}
                        </p>
                    @endif

                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        Conducteur de {{ $vehicule->min_driver_age }} ans minimum, permis depuis
                        {{ $vehicule->min_license_years }} an(s) au jour du départ.
                    </p>
                </div>
            </div>

            {{-- ── Le formulaire ──────────────────────────────────────────────────── --}}
            <form wire:submit="reserver" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">

                @if ($erreur)
                    <p class="rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $erreur }}</p>
                @endif

                <fieldset class="space-y-3">
                    <legend class="text-sm font-black uppercase tracking-wide text-slate-900 dark:text-white">Votre période</legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Départ</span>
                            <input type="datetime-local" wire:model.live.debounce.400ms="debut"
                                class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('debut') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Retour</span>
                            <input type="datetime-local" wire:model.live.debounce.400ms="fin"
                                class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('fin') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        {{ $devis['days'] }} jour(s) facturé(s) —
                        <span class="font-semibold">toute journée entamée est due</span>, comme dans toute agence.
                    </p>
                </fieldset>

                {{-- LES DEUX PRIX, CÔTE À CÔTE. Le supplément seul ne veut rien dire ; en regard de
                     la caution qu'il fait tomber, il devient un arbitrage. --}}
                <fieldset class="space-y-3">
                    <legend class="text-sm font-black uppercase tracking-wide text-slate-900 dark:text-white">Votre garantie</legend>

                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition
                        @if ($protection === \App\Models\RentalVehicle::PROTECTION_SANS) border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30 @else border-slate-200 dark:border-slate-700 @endif">
                        <input type="radio" wire:model.live="protection" value="{{ \App\Models\RentalVehicle::PROTECTION_SANS }}" class="mt-1">
                        <span class="flex-1">
                            <span class="block font-bold text-slate-900 dark:text-white">Sans garantie</span>
                            <span class="block text-sm text-slate-600 dark:text-slate-300">
                                {{ number_format($devis['sans_garantie']['total_cents'] / 100, 2, ',', ' ') }} {{ $devis['currency'] }}
                                · caution {{ number_format($devis['sans_garantie']['deposit_cents'] / 100, 0, ',', ' ') }} {{ $devis['currency'] }}
                            </span>
                        </span>
                    </label>

                    @if ($devis['propose_une_garantie'])
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition
                            @if ($protection === \App\Models\RentalVehicle::PROTECTION_AVEC) border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30 @else border-slate-200 dark:border-slate-700 @endif">
                            <input type="radio" wire:model.live="protection" value="{{ \App\Models\RentalVehicle::PROTECTION_AVEC }}" class="mt-1">
                            <span class="flex-1">
                                <span class="block font-bold text-slate-900 dark:text-white">Avec garantie</span>
                                <span class="block text-sm text-slate-600 dark:text-slate-300">
                                    {{ number_format($devis['avec_garantie']['total_cents'] / 100, 2, ',', ' ') }} {{ $devis['currency'] }}
                                    · caution réduite à {{ number_format($devis['avec_garantie']['deposit_cents'] / 100, 0, ',', ' ') }} {{ $devis['currency'] }}
                                </span>
                            </span>
                        </label>
                    @endif
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-black uppercase tracking-wide text-slate-900 dark:text-white">Le conducteur</legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Prénom</span>
                            <input type="text" wire:model="driverFirstName" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('driverFirstName') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Nom</span>
                            <input type="text" wire:model="driverLastName" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('driverLastName') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Date de naissance</span>
                            <input type="date" wire:model="driverBirthdate" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('driverBirthdate') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Téléphone</span>
                            <input type="tel" wire:model="driverPhone" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Adresse e-mail</span>
                            <input type="email" wire:model="driverEmail" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('driverEmail') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-black uppercase tracking-wide text-slate-900 dark:text-white">Le permis</legend>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Numéro</span>
                            <input type="text" wire:model="licenseNumber" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('licenseNumber') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Pays</span>
                            <input type="text" maxlength="2" wire:model="licenseCountry" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase dark:bg-slate-800 dark:text-white">
                        </label>
                        <label class="block sm:col-span-3">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Date d’obtention</span>
                            <input type="date" wire:model="licenseIssuedAt" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
                            @error('licenseIssuedAt') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </fieldset>

                <button type="submit"
                    class="min-h-[48px] w-full rounded-2xl bg-slate-900 px-6 text-base font-black text-white transition hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                    Voir le récapitulatif
                </button>

                <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                    Aucun paiement à cette étape&nbsp;: le règlement se fait à l’agence, au retrait.
                </p>
            </form>
        </div>
    </div>
</div>
