<div class="space-y-6">

    <x-page-shell
        eyebrow="Location entre membres"
        title="Louez la voiture d’un voisin"
        subtitle="Des véhicules proposés par des membres de la plateforme. Paiement bloqué jusqu’à la remise des clés.">
        <div class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="peer-lieu" class="brio-field-label">{{ __('Où') }}</label>
                <input id="peer-lieu" type="text" wire:model.live.debounce.400ms="lieu"
                       placeholder="{{ __('Ville ou code postal') }}">
            </div>
            <div>
                <label for="peer-du" class="brio-field-label">{{ __('Du') }}</label>
                <input id="peer-du" type="date" wire:model.live="debut">
            </div>
            <div>
                <label for="peer-au" class="brio-field-label">{{ __('Au') }}</label>
                <input id="peer-au" type="date" wire:model.live="fin">
            </div>
        </div>
    </x-page-shell>

    <div class="grid gap-4 lg:grid-cols-4">

        {{-- Filtres --}}
        <x-app-card title="Filtres" class="lg:col-span-1 h-fit">
            <div class="space-y-4">
                <div>
                    <label for="peer-rayon" class="brio-field-label">{{ __('Rayon') }} — {{ $rayonKm }} km</label>
                    <input id="peer-rayon" type="range" min="5" max="150" step="5"
                           wire:model.live="rayonKm" class="w-full accent-sky-600">
                </div>

                <div>
                    <label for="peer-cat" class="brio-field-label">{{ __('Catégorie') }}</label>
                    <select id="peer-cat" wire:model.live="categorie">
                        <option value="">{{ __('Toutes') }}</option>
                        @foreach ($this->categories as $categorie)
                            <option value="{{ $categorie }}">{{ ucfirst($categorie) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="peer-boite" class="brio-field-label">{{ __('Boîte') }}</label>
                    <select id="peer-boite" wire:model.live="transmission">
                        <option value="">{{ __('Indifférent') }}</option>
                        <option value="manuelle">{{ __('Manuelle') }}</option>
                        <option value="automatique">{{ __('Automatique') }}</option>
                    </select>
                </div>

                <div>
                    <label for="peer-energie" class="brio-field-label">{{ __('Énergie') }}</label>
                    <select id="peer-energie" wire:model.live="carburant">
                        <option value="">{{ __('Indifférente') }}</option>
                        @foreach (['essence', 'diesel', 'hybride', 'electrique', 'gpl'] as $energie)
                            <option value="{{ $energie }}">{{ ucfirst($energie) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="peer-places" class="brio-field-label">{{ __('Places') }}</label>
                        <input id="peer-places" type="number" min="2" max="9" wire:model.live.debounce.400ms="placesMin">
                    </div>
                    <div>
                        <label for="peer-max" class="brio-field-label">{{ __('Prix max / j') }}</label>
                        <input id="peer-max" type="number" min="10" step="5" wire:model.live.debounce.400ms="prixMax">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" wire:model.live="reservationImmediate" class="rounded border-slate-300 text-sky-600">
                    {{ __('Réservation immédiate') }}
                </label>

                <button type="button" wire:click="reinitialiserLesFiltres" class="brio-btn-secondary w-full !text-xs">
                    {{ __('Tout effacer') }}
                </button>
            </div>
        </x-app-card>

        {{-- Résultats --}}
        <div class="space-y-4 lg:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">
                    {{ trans_choice(':count véhicule disponible|:count véhicules disponibles', $this->vehicules->count(), ['count' => $this->vehicules->count()]) }}
                    @if ($this->periode())
                        · {{ $this->periode()['debut']->translatedFormat('d M') }} → {{ $this->periode()['fin']->translatedFormat('d M') }}
                    @endif
                </p>

                <select wire:model.live="tri" class="!w-auto !py-1.5 !text-xs" aria-label="{{ __('Trier') }}">
                    <option value="pertinence">{{ __('Les plus récents') }}</option>
                    <option value="prix">{{ __('Prix croissant') }}</option>
                    <option value="prix_desc">{{ __('Prix décroissant') }}</option>
                    <option value="distance">{{ __('Les plus proches') }}</option>
                </select>
            </div>

            @forelse ($this->vehicules as $vehicule)
                @if ($loop->first)
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @endif

                <a href="{{ route('peer.vehicule', $vehicule) }}" class="brio-card block overflow-hidden !p-0 transition">
                    <div class="aspect-[16/10] w-full overflow-hidden bg-slate-100">
                        @if ($photo = $vehicule->photoPrincipale())
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}"
                                 alt="{{ $vehicule->titre() }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="flex h-full flex-col items-center justify-center gap-1">
                                <span class="text-3xl" aria-hidden="true">🚗</span>
                                <span class="text-[11px] text-slate-500">{{ __('Photos à venir') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-bold text-slate-900">{{ $vehicule->titre() }}</h3>
                                <p class="text-xs text-slate-500">
                                    {{ $vehicule->city }}
                                    @if ($vehicule->getAttribute('distance_km') !== null)
                                        · {{ $vehicule->getAttribute('distance_km') }} km
                                    @endif
                                </p>
                            </div>
                            @if ($vehicule->instant_booking)
                                <x-ui.badge tone="success" label="{{ __('Immédiat') }}" class="flex-shrink-0" />
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <span class="brio-chip !px-2 !py-0.5 !text-[10px]">{{ ucfirst($vehicule->transmission) }}</span>
                            <span class="brio-chip !px-2 !py-0.5 !text-[10px]">{{ ucfirst($vehicule->fuel) }}</span>
                            <span class="brio-chip !px-2 !py-0.5 !text-[10px]">{{ $vehicule->seats }} {{ __('places') }}</span>
                        </div>

                        <p class="text-sm font-black text-slate-900">
                            <x-money :amount="$vehicule->daily_price_cents / 100" :currency="$vehicule->currency" />
                            <span class="text-xs font-medium text-slate-500">/ {{ __('jour') }}</span>
                        </p>
                    </div>
                </a>

                @if ($loop->last)
                    </div>
                @endif
            @empty
                <x-empty-state
                    icon="🚗"
                    title="{{ __('Aucun véhicule sur ces critères') }}"
                    message="{{ __('Élargissez le rayon, changez les dates, ou retirez un filtre.') }}">
                    <button type="button" wire:click="reinitialiserLesFiltres" class="brio-btn-primary !text-xs">
                        {{ __('Effacer les filtres') }}
                    </button>
                </x-empty-state>
            @endforelse
        </div>
    </div>
</div>
