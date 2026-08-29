@php($vehicule = $this->vehicule())

<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Location entre membres')"
        :title="$vehicule->titre()"
        :subtitle="$vehicule->city . ' · ' . ucfirst($vehicule->category) . ' · ' . $vehicule->year">
        <x-slot name="actions">
            <a href="{{ route('peer.catalogue') }}" class="brio-btn-secondary !text-xs">← {{ __('Tous les véhicules') }}</a>
        </x-slot>
    </x-page-shell>

    <div class="grid gap-4 lg:grid-cols-3">

        <div class="space-y-4 lg:col-span-2">

            {{-- Les photos --}}
            <x-app-card class="!p-0 overflow-hidden">
                @if ($photo = $vehicule->photoPrincipale())
                    <div class="aspect-[16/9] w-full overflow-hidden bg-slate-100">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}"
                             alt="{{ $vehicule->titre() }}" class="h-full w-full object-cover">
                    </div>
                @else
                    <div class="flex aspect-[16/9] flex-col items-center justify-center gap-2 bg-slate-100">
                        <span class="text-5xl" aria-hidden="true">🚗</span>
                        <p class="text-sm text-slate-500">{{ __('Photos à venir') }}</p>
                    </div>
                @endif

                @if ($vehicule->media->count() > 1)
                    <div class="flex gap-2 overflow-x-auto p-3">
                        @foreach ($vehicule->media as $image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($image->path) }}"
                                 alt="" loading="lazy"
                                 class="h-16 w-24 flex-shrink-0 rounded-xl object-cover">
                        @endforeach
                    </div>
                @endif
            </x-app-card>

            <x-app-card :title="__('Le véhicule')">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ([
                        ['🔧', __('Boîte'), ucfirst($vehicule->transmission)],
                        ['⛽', __('Énergie'), ucfirst($vehicule->fuel)],
                        ['👥', __('Places'), (string) $vehicule->seats],
                        ['🧳', __('Bagages'), (string) $vehicule->luggage],
                    ] as [$icone, $libelle, $valeur])
                        <div class="brio-list-item !p-3 text-center">
                            <p class="text-xl" aria-hidden="true">{{ $icone }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-900">{{ $valeur }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ $libelle }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($vehicule->description)
                    <p class="mt-4 text-sm leading-6 text-slate-600">{{ $vehicule->description }}</p>
                @endif

                @if ($vehicule->equipements() !== [])
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($vehicule->equipements() as $equipement)
                            <span class="brio-chip !px-2 !py-0.5 !text-[10px]">{{ $equipement }}</span>
                        @endforeach
                    </div>
                @endif
            </x-app-card>

            <x-app-card :title="__('Conditions')">
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        [__('Kilomètres inclus'), $vehicule->included_km_per_day . ' km / ' . __('jour')],
                        [__('Km supplémentaire'), locale_currency($vehicule->extra_km_price_cents / 100, $vehicule->currency)],
                        [__('Caution'), locale_currency($vehicule->deposit_cents / 100, $vehicule->currency)],
                        [__('Âge minimum'), $vehicule->min_driver_age . ' ' . __('ans')],
                        [__('Permis depuis'), $vehicule->min_license_years . ' ' . __('an(s)')],
                        [__('Annulation'), match ($vehicule->cancellation_policy) {
                            'souple' => __('Souple — remboursé jusqu’à 24 h avant'),
                            'stricte' => __('Stricte — remboursé jusqu’à 7 jours avant'),
                            default => __('Modérée — remboursé jusqu’à 72 h avant'),
                        }],
                    ] as [$terme, $valeur])
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $terme }}</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $valeur }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-app-card>

            <x-app-card :title="__('Le propriétaire')">
                <div class="flex items-center gap-3">
                    <img src="{{ $vehicule->owner->profile_photo_url }}" alt=""
                         class="h-12 w-12 rounded-full border border-slate-200 object-cover">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900">{{ $vehicule->owner->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ __('Membre depuis') }} {{ $vehicule->owner->created_at?->translatedFormat('F Y') }}
                            @if ($this->noteDuProprietaire())
                                · ⭐ {{ number_format($this->noteDuProprietaire(), 1, ',', ' ') }}/5
                            @endif
                        </p>
                    </div>
                </div>
            </x-app-card>
        </div>

        {{-- La réservation --}}
        <div class="space-y-4">
            <x-app-card class="lg:sticky lg:top-20">
                <p class="text-2xl font-black text-slate-900">
                    <x-money :amount="$vehicule->daily_price_cents / 100" :currency="$vehicule->currency" />
                    <span class="text-sm font-medium text-slate-500">/ {{ __('jour') }}</span>
                </p>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <label for="peer-fiche-du" class="brio-field-label">{{ __('Départ') }}</label>
                        <input id="peer-fiche-du" type="date" wire:model.live="debut" min="{{ now()->toDateString() }}">
                    </div>
                    <div>
                        <label for="peer-fiche-au" class="brio-field-label">{{ __('Retour') }}</label>
                        <input id="peer-fiche-au" type="date" wire:model.live="fin" min="{{ now()->addDay()->toDateString() }}">
                    </div>
                </div>

                @if ($vehicule->delivery_enabled)
                    <label class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model.live="livraison" class="rounded border-slate-300 text-sky-600">
                        {{ __('Livraison') }} (+{{ locale_currency($vehicule->delivery_price_cents / 100, $vehicule->currency) }})
                    </label>

                    @if ($livraison)
                        <div class="mt-2">
                            <label for="peer-adresse" class="brio-field-label">{{ __('Adresse de livraison') }}</label>
                            <input id="peer-adresse" type="text" wire:model.blur="adresseLivraison">
                        </div>
                    @endif
                @endif

                @if (config('peer_rental.insurance.enabled'))
                    <div class="mt-3">
                        <label for="peer-assurance" class="brio-field-label">{{ __('Protection') }}</label>
                        <select id="peer-assurance" wire:model.live="assurance">
                            <option value="">{{ __('Aucune') }}</option>
                            @foreach (config('peer_rental.insurance.plans', []) as $cle => $formule)
                                <option value="{{ $cle }}">
                                    {{ $formule['label'] }} — {{ locale_currency($formule['daily_cents'] / 100) }}/{{ __('jour') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($devis = $this->devis())
                    <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-600">{{ $devis['days'] }} {{ __('jour(s)') }}</span>
                            <span class="font-semibold text-slate-900">
                                <x-money :amount="$devis['subtotal_cents'] / 100" :currency="$devis['currency']" />
                            </span>
                        </div>

                        @if ($devis['discount_cents'] > 0)
                            <div class="flex justify-between text-emerald-700">
                                <span>{{ __('Remise longue durée') }} ({{ $devis['discount_percent'] }} %)</span>
                                <span>− <x-money :amount="$devis['discount_cents'] / 100" :currency="$devis['currency']" /></span>
                            </div>
                        @endif

                        @if ($devis['delivery_cents'] > 0)
                            <div class="flex justify-between">
                                <span class="text-slate-600">{{ __('Livraison') }}</span>
                                <span class="font-semibold text-slate-900">
                                    <x-money :amount="$devis['delivery_cents'] / 100" :currency="$devis['currency']" />
                                </span>
                            </div>
                        @endif

                        @if ($devis['insurance_cents'] > 0)
                            <div class="flex justify-between">
                                <span class="text-slate-600">{{ __('Protection') }}</span>
                                <span class="font-semibold text-slate-900">
                                    <x-money :amount="$devis['insurance_cents'] / 100" :currency="$devis['currency']" />
                                </span>
                            </div>
                        @endif

                        <div class="flex justify-between border-t border-slate-100 pt-2 text-base">
                            <span class="font-bold text-slate-900">{{ __('Total') }}</span>
                            <span class="font-black text-slate-900">
                                <x-money :amount="$devis['total_cents'] / 100" :currency="$devis['currency']" />
                            </span>
                        </div>

                        <p class="text-xs text-slate-500">
                            {{ __('Caution bloquée à la remise') }} :
                            <x-money :amount="$devis['deposit_cents'] / 100" :currency="$devis['currency']" />
                            · {{ $devis['included_km'] }} km {{ __('inclus') }}
                        </p>
                    </div>
                @endif

                @if ($motif = $this->indisponibilite())
                    <div class="brio-alerte brio-alerte-warning mt-4 !mb-0">
                        <span aria-hidden="true">📅</span>
                        <span>{{ $motif }}</span>
                    </div>
                @elseif ($blocage = $this->blocageConducteur())
                    <div class="brio-alerte brio-alerte-warning mt-4 !mb-0">
                        <span aria-hidden="true">🪪</span>
                        <span>{{ $blocage }}</span>
                    </div>
                @endif

                @if ($erreur)
                    <div class="brio-alerte brio-alerte-danger mt-4 !mb-0">
                        <span aria-hidden="true">⚠️</span>
                        <span>{{ $erreur }}</span>
                    </div>
                @endif

                @auth
                    <div class="mt-4">
                        <label for="peer-carte" class="brio-field-label">{{ __('Moyen de paiement') }}</label>
                        {{-- Stripe Elements pose son jeton ici ; la carte ne touche jamais nos serveurs. --}}
                        <input id="peer-carte" type="text" wire:model.blur="paymentMethodId"
                               placeholder="{{ __('Carte enregistrée') }}" data-stripe-payment-method>
                    </div>

                    <button type="button" wire:click="reserver" wire:loading.attr="disabled"
                            class="brio-btn-primary mt-4 w-full"
                            @disabled($this->indisponibilite() !== null || $this->blocageConducteur() !== null || $this->devis() === null)>
                        <span wire:loading.remove wire:target="reserver">
                            {{ $vehicule->instant_booking ? __('Réserver maintenant') : __('Envoyer la demande') }}
                        </span>
                        <span wire:loading wire:target="reserver">{{ __('Un instant…') }}</span>
                    </button>

                    <p class="mt-3 text-center text-xs leading-5 text-slate-500">
                        {{ __('Les fonds sont bloqués, pas encaissés. Ils ne partent qu’à la remise des clés, quand vous et le propriétaire l’aurez confirmée.') }}
                    </p>
                @else
                    <a href="{{ route('login') }}" class="brio-btn-primary mt-4 block w-full text-center">
                        {{ __('Se connecter pour réserver') }}
                    </a>
                @endauth
            </x-app-card>
        </div>
    </div>
</div>
