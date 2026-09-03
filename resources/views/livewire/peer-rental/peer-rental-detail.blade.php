@php
    $location = $this->location();
    // LE BIEN, PAS LA VOITURE. Rendre `vehicle` ici plantait la page des qu un logement
    // empruntait le meme chemin d argent - et c est justement ce que ce chemin autorise.
    $bien = $location->bien();
    $vehicule = $bien instanceof \App\Models\PeerVehicle ? $bien : null;
    $phase = $this->phase();
    $etat = $this->etatDesLieux();

    $tons = [
        'pending_owner' => 'warning',
        'confirmed' => 'info',
        'handed_over' => 'success',
        'returned' => 'info',
        'completed' => 'success',
        'disputed' => 'danger',
        'cancelled' => 'neutral',
        'declined' => 'neutral',
        'expired' => 'neutral',
    ];

    $libelles = [
        'pending_owner' => __('En attente du propriétaire'),
        'confirmed' => __('Confirmée'),
        'handed_over' => __('En cours'),
        'returned' => __('Rendue'),
        'completed' => __('Terminée'),
        'disputed' => __('Litige en cours'),
        'cancelled' => __('Annulée'),
        'declined' => __('Refusée'),
        'expired' => __('Expirée'),
    ];
@endphp

<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Location') . ' ' . $location->reference"
        :title="$bien?->titre() ?? $location->reference"
        :subtitle="$location->starts_at->translatedFormat('d M Y, H:i') . ' → ' . $location->ends_at->translatedFormat('d M Y, H:i')">
        <x-slot name="actions">
            <x-ui.badge :tone="$tons[$location->status] ?? 'neutral'" :label="$libelles[$location->status] ?? $location->status" />
        </x-slot>
    </x-page-shell>

    @if ($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif

    @if ($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">

        <div class="space-y-4 lg:col-span-2">

            {{-- LA DEMANDE, COTE PROPRIETAIRE --}}
            @if ($location->status === \App\Models\PeerRental::STATUT_EN_ATTENTE && $this->estLeProprietaire())
                <x-app-card :title="__('Une demande vous attend')">
                    <p class="text-sm leading-6 text-slate-600">
                        {{ __('Les fonds du locataire sont déjà bloqués. Ils ne partiront qu’à la remise des clés — et vous reviendront si vous refusez.') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" wire:click="accepter" class="brio-btn-primary !text-xs">{{ __('Accepter') }}</button>
                        <button type="button" wire:click="refuser" class="brio-btn-danger !text-xs">{{ __('Refuser') }}</button>
                    </div>
                </x-app-card>
            @endif

            {{-- L'ETAT DES LIEUX --}}
            @if ($phase !== 'aucune')
                <x-app-card :title="$phase === 'departure' ? __('État des lieux de départ') : __('État des lieux de retour')"
                            :subtitle="__('À remplir ensemble, avant de confirmer.')">

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="peer-km" class="brio-field-label">{{ __('Kilométrage') }}</label>
                            <input id="peer-km" type="number" min="0" wire:model="kilometrage"
                                   value="{{ $etat?->mileage_km }}">
                            @error('kilometrage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="peer-carburant" class="brio-field-label">{{ __('Carburant (huitièmes)') }}</label>
                            <input id="peer-carburant" type="number" min="0" max="8" wire:model="carburantHuitiemes">
                        </div>
                        <div>
                            <label for="peer-proprete" class="brio-field-label">{{ __('Propreté') }}</label>
                            <select id="peer-proprete" wire:model="proprete">
                                <option value="propre">{{ __('Propre') }}</option>
                                <option value="acceptable">{{ __('Acceptable') }}</option>
                                <option value="sale">{{ __('Sale') }}</option>
                            </select>
                        </div>
                    </div>

                    @if ($phase === 'departure' && $this->estLeProprietaire())
                        <label class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="permisVerifie" class="rounded border-slate-300 text-sky-600">
                            {{ __('J’ai vérifié le permis de conduire du locataire') }}
                        </label>
                    @endif

                    <div class="mt-3">
                        <label for="peer-remarques" class="brio-field-label">{{ __('Remarques') }}</label>
                        <textarea id="peer-remarques" rows="2" wire:model="remarques"></textarea>
                    </div>

                    <div class="mt-4">
                        <p class="brio-field-label">{{ __('Photos — les six angles sont exigés') }}</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach (\App\Models\PeerInspection::ANGLES_REQUIS as $angle)
                                @php($deja = $etat?->photos->firstWhere('angle', $angle))
                                <div class="brio-list-item !p-3">
                                    <p class="text-xs font-semibold text-slate-700">{{ ucfirst($angle) }}</p>
                                    @if ($deja)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($deja->path) }}" alt=""
                                             class="mt-2 h-20 w-full rounded-lg object-cover">
                                    @else
                                        <input type="file" accept="image/*" wire:model="photos.{{ $angle }}" class="mt-2 text-xs">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button type="button" wire:click="enregistrerLEtatDesLieux" class="brio-btn-secondary mt-4 !text-xs">
                        {{ __('Enregistrer l’état des lieux') }}
                    </button>
                </x-app-card>

                {{-- LA CONFIRMATION A DEUX --}}
                <x-app-card :title="$phase === 'departure' ? __('Remise des clés') : __('Retour du véhicule')">
                    {{-- La forme EN LIGNE, exclusivement. Un bloc PHP ouvert plus bas dans la
                         vue se fait fermer par le premier `@php(…)` rencontre plus haut, et
                         Blade avale alors tout ce qui les separe — commentaires compris. --}}
                    @php($signatureProprietaire = $phase === 'departure' ? $location->handover_owner_confirmed_at : $location->return_owner_confirmed_at)
                    @php($signatureLocataire = $phase === 'departure' ? $location->handover_renter_confirmed_at : $location->return_renter_confirmed_at)

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            [__('Propriétaire'), $signatureProprietaire],
                            [__('Locataire'), $signatureLocataire],
                        ] as [$qui, $quand])
                            <div class="brio-list-item flex items-center justify-between !p-3">
                                <span class="text-sm font-semibold text-slate-700">{{ $qui }}</span>
                                @if ($quand)
                                    <x-ui.badge tone="success" :label="$quand->translatedFormat('d/m H:i')" />
                                @else
                                    <x-ui.badge tone="neutral" :label="__('En attente')" />
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($this->estLeLocataire())
                        <div class="mt-4">
                            @if ($codeEnClair)
                                <p class="brio-field-label">{{ __('Montrez ce code au propriétaire') }}</p>
                                <p class="text-3xl font-black tracking-[0.4em] text-slate-900 tabular-nums">{{ $codeEnClair }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Valable :h heures.', ['h' => config('peer_rental.code_ttl_hours', 12)]) }}</p>
                            @else
                                <button type="button" wire:click="afficherLeCode" class="brio-btn-secondary !text-xs">
                                    {{ __('Afficher mon code') }}
                                </button>
                            @endif
                        </div>
                    @endif

                    @if ($this->estLeProprietaire())
                        <div class="mt-4">
                            <label for="peer-code" class="brio-field-label">{{ __('Code affiché par le locataire') }}</label>
                            <input id="peer-code" type="text" inputmode="numeric" maxlength="6" wire:model="codeSaisi"
                                   class="tracking-[0.3em]">
                        </div>
                    @endif

                    <button type="button" wire:click="confirmer" class="brio-btn-primary mt-4 w-full sm:w-auto">
                        {{ $phase === 'departure' ? __('Je confirme la remise') : __('Je confirme le retour') }}
                    </button>

                    @if ($phase === 'departure')
                        <p class="mt-3 text-xs leading-5 text-slate-500">
                            {{ __('Le paiement est capturé quand vous aurez confirmé tous les deux. Une seule confirmation ne prélève rien.') }}
                        </p>
                    @endif
                </x-app-card>
            @endif

            {{-- LES SUPPLEMENTS ET LES RETENUES --}}
            @if (in_array($location->status, ['handed_over', 'returned', 'disputed', 'completed'], true))
                <x-app-card :title="__('Suppléments et retenues')"
                            :subtitle="__('Mesurés sur les deux états des lieux, retenus sur la caution.')">

                    @if ($this->supplements()['lignes'] !== [])
                        <div class="space-y-2">
                            @foreach ($this->supplements()['lignes'] as $ligne)
                                <div class="brio-list-item flex items-center justify-between gap-3 !p-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $ligne['libelle'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $ligne['detail'] }}</p>
                                    </div>
                                    <span class="flex-shrink-0 text-sm font-black text-slate-900">
                                        <x-money :amount="$ligne['cents'] / 100" :currency="$location->currency" />
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        @if ($this->estLeProprietaire() && $location->status !== 'completed')
                            <button type="button" wire:click="reclamerLesSupplements" class="brio-btn-secondary mt-3 !text-xs">
                                {{ __('Réclamer ces suppléments') }}
                            </button>
                        @endif
                    @endif

                    @forelse ($location->claims as $retenue)
                        <div class="brio-list-item mt-3 !p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ ucfirst($retenue->kind) }} —
                                        <x-money :amount="$retenue->amount_cents / 100" :currency="$location->currency" />
                                    </p>
                                    @if ($retenue->description)
                                        <p class="text-xs text-slate-500">{{ $retenue->description }}</p>
                                    @endif
                                </div>
                                <x-ui.badge
                                    :tone="match ($retenue->status) { 'accepted', 'resolved' => 'success', 'disputed' => 'danger', 'withdrawn' => 'neutral', default => 'warning' }"
                                    :label="ucfirst($retenue->status)" class="flex-shrink-0" />
                            </div>

                            @if ($retenue->status === 'open' && $this->estLeLocataire())
                                <div class="mt-3 flex gap-2">
                                    <button type="button" wire:click="accepterLaRetenue({{ $retenue->id }})" class="brio-btn-secondary !text-xs">
                                        {{ __('J’accepte') }}
                                    </button>
                                    <button type="button" wire:click="contesterLaRetenue({{ $retenue->id }})" class="brio-btn-danger !text-xs">
                                        {{ __('Je conteste') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        @if ($this->supplements()['lignes'] === [])
                            <p class="text-sm text-slate-500">{{ __('Rien à signaler pour le moment.') }}</p>
                        @endif
                    @endforelse

                    @if ($this->estLeProprietaire() && in_array($location->status, ['handed_over', 'returned', 'disputed'], true))
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <p class="brio-field-label">{{ __('Demander une retenue') }}</p>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <select wire:model="motifRetenue" aria-label="{{ __('Motif') }}">
                                    <option value="damage">{{ __('Dommage') }}</option>
                                    <option value="cleaning">{{ __('Nettoyage') }}</option>
                                    <option value="fuel">{{ __('Carburant') }}</option>
                                    <option value="late_return">{{ __('Retard') }}</option>
                                    <option value="mileage">{{ __('Kilométrage') }}</option>
                                </select>
                                <input type="number" step="0.01" min="0.01" wire:model="montantRetenue"
                                       placeholder="{{ __('Montant €') }}" aria-label="{{ __('Montant') }}">
                                <button type="button" wire:click="ouvrirUneRetenue" class="brio-btn-secondary !text-xs">
                                    {{ __('Déposer') }}
                                </button>
                            </div>
                            <textarea rows="2" wire:model="descriptionRetenue" class="mt-2"
                                      placeholder="{{ __('Ce que vous avez constaté') }}"></textarea>
                        </div>
                    @endif
                </x-app-card>
            @endif

            {{-- LES AVIS --}}
            @if (in_array($location->status, ['returned', 'completed', 'disputed'], true))
                <x-app-card :title="__('Avis')" :subtitle="__('Révélés quand vous aurez tous les deux donné le vôtre.')">
                    @php($monAvis = $location->reviews->firstWhere('author_id', auth()->id()))

                    @if ($monAvis)
                        <p class="text-sm text-slate-600">
                            {{ __('Votre avis') }} : {{ str_repeat('★', $monAvis->rating) }}
                            @if ($monAvis->revealed_at)
                                · <span class="text-emerald-700">{{ __('publié') }}</span>
                            @else
                                · <span class="text-amber-700">{{ __('en attente de l’autre avis') }}</span>
                            @endif
                        </p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-4">
                            <select wire:model="noteAvis" aria-label="{{ __('Note') }}">
                                @foreach (range(5, 1) as $note)
                                    <option value="{{ $note }}">{{ str_repeat('★', $note) }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="commentaireAvis" class="sm:col-span-2"
                                   placeholder="{{ __('Votre commentaire') }}" aria-label="{{ __('Commentaire') }}">
                            <button type="button" wire:click="deposerUnAvis" class="brio-btn-primary !text-xs">
                                {{ __('Publier') }}
                            </button>
                        </div>
                    @endif

                    @foreach ($location->reviews->whereNotNull('revealed_at')->where('author_id', '!=', auth()->id()) as $avis)
                        <div class="brio-list-item mt-3 !p-3">
                            <p class="text-sm font-semibold text-slate-900">{{ str_repeat('★', $avis->rating) }}</p>
                            @if ($avis->comment)
                                <p class="mt-1 text-sm text-slate-600">{{ $avis->comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </x-app-card>
            @endif
        </div>

        {{-- LE RECAPITULATIF --}}
        <div class="space-y-4">
            <x-app-card :title="__('Le compte')">
                <dl class="space-y-2 text-sm">
                    @foreach ([
                        [__('Loyer'), $location->subtotal_cents],
                        [__('Remise'), -$location->discount_cents],
                        [__('Livraison'), $location->delivery_cents],
                        [__('Protection'), $location->insurance_cents],
                    ] as [$terme, $cents])
                        @if ($cents != 0)
                            <div class="flex justify-between">
                                <dt class="text-slate-600">{{ $terme }}</dt>
                                <dd class="font-semibold text-slate-900">
                                    <x-money :amount="$cents / 100" :currency="$location->currency" />
                                </dd>
                            </div>
                        @endif
                    @endforeach

                    <div class="flex justify-between border-t border-slate-100 pt-2">
                        <dt class="font-bold text-slate-900">{{ __('Total') }}</dt>
                        <dd class="font-black text-slate-900">
                            <x-money :amount="$location->total_cents / 100" :currency="$location->currency" />
                        </dd>
                    </div>

                    @if ($this->estLeProprietaire())
                        <div class="flex justify-between text-emerald-700">
                            <dt>{{ __('Ce que vous recevez') }}</dt>
                            <dd class="font-bold">
                                <x-money :amount="$location->owner_payout_cents / 100" :currency="$location->currency" />
                            </dd>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ __('Commission plateforme') }} :
                            <x-money :amount="$location->platform_fee_cents / 100" :currency="$location->currency" />
                            ({{ round($location->commission_rate * 100) }} %)
                        </p>
                    @endif
                </dl>

                <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">{{ __('Caution') }}</span>
                        <span class="font-semibold text-slate-900">
                            <x-money :amount="$location->deposit_cents / 100" :currency="$location->currency" />
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($location->deposit_released_at)
                            {{ $location->deposit_captured_cents > 0
                                ? __('Retenue appliquée, solde libéré.')
                                : __('Libérée intégralement.') }}
                        @elseif ($location->deposit_authorized_at)
                            {{ __('Bloquée jusqu’au retour.') }}
                        @else
                            {{ __('Sera bloquée à la remise des clés.') }}
                        @endif
                    </p>
                </div>

                @if (in_array($location->status, ['pending_owner', 'confirmed'], true))
                    <button type="button" wire:click="annuler" class="brio-btn-danger mt-4 w-full !text-xs">
                        {{ $this->estLeProprietaire() ? __('Me désister') : __('Annuler ma location') }}
                    </button>
                    @if ($this->estLeLocataire() && $bien)
                        <p class="mt-2 text-center text-xs text-slate-500">
                            {{ __('Barème :politique.', ['politique' => $bien->politiqueDAnnulation()]) }}
                        </p>
                    @endif
                @endif
            </x-app-card>

            <x-app-card :title="$vehicule ? __('Le véhicule') : __('Le logement')">
                @if ($bien)
                    <a href="{{ $bien->urlPublique() }}" class="flex items-center gap-3">
                        @if ($couverture = $bien->photoDeCouverture())
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($couverture) }}" alt=""
                                 class="h-14 w-20 flex-shrink-0 rounded-xl object-cover">
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-slate-900">{{ $bien->titre() }}</p>
                            {{-- LA PLAQUE N EXISTE QUE POUR UNE VOITURE ; un logement dit sa ville. --}}
                            <p class="text-xs text-slate-500">
                                {{ collect($vehicule ? [$vehicule->plate, $vehicule->city] : [$bien->city, $bien->country_code])
                                    ->filter()->join(' · ') }}
                            </p>
                        </div>
                    </a>
                @endif

                <div class="mt-3 border-t border-slate-100 pt-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        {{ $this->estLeProprietaire() ? __('Locataire') : __('Propriétaire') }}
                    </p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ $this->estLeProprietaire() ? $location->renter->name : $location->owner->name }}
                    </p>
                </div>
            </x-app-card>
        </div>
    </div>
</div>
