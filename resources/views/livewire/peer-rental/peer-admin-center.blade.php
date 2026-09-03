<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Administration')"
        :title="__('Location entre membres')"
        :subtitle="__('Les annonces de véhicules et de logements à vérifier, les papiers à valider, les retenues contestées.')" />

    @if ($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif
    @if ($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-ui.stat :title="__('Véhicules en ligne')" :value="$this->chiffres['vehicules']" icon="🚗" tone="blue" />
        <x-ui.stat :title="__('Logements en ligne')" :value="$this->chiffres['logements']" icon="🏠" tone="blue" />
        <x-ui.stat :title="__('Locations en cours')" :value="$this->chiffres['locations_en_cours']" icon="🔑" tone="emerald" />
        <x-ui.stat :title="__('Commission encaissée')"
                   :value="locale_currency($this->chiffres['commission_cents'] / 100)" icon="💶" tone="emerald" />
        <x-ui.stat :title="__('Litiges')" :value="$this->chiffres['litiges']" icon="⚠️" tone="red" />
    </div>

    <x-app-card>
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'annonces' => __('Véhicules') . ' (' . $this->annoncesAVerifier->count() . ')',
                'logements' => __('Logements') . ' (' . $this->logementsAVerifier->count() . ')',
                'papiers' => __('Papiers') . ' (' . $this->papiersAValider->count() . ')',
                'litiges' => __('Litiges') . ' (' . $this->retenuesContestees->count() . ')',
            ] as $cle => $libelle)
                <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                        class="{{ $onglet === $cle ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                    {{ $libelle }}
                </button>
            @endforeach
        </div>

        @if ($onglet === 'annonces')
            <div class="space-y-2">
                @forelse ($this->annoncesAVerifier as $vehicule)
                    <div class="brio-list-item !p-3">
                        <div class="flex items-start gap-3">
                            @if ($photo = $vehicule->photoPrincipale())
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}" alt=""
                                     class="h-14 w-20 flex-shrink-0 rounded-lg object-cover">
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $vehicule->titre() }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $vehicule->owner?->name }} · {{ $vehicule->city }} · {{ $vehicule->plate }}
                                    · {{ trans_choice(':count photo|:count photos', $vehicule->media->count(), ['count' => $vehicule->media->count()]) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ __('Papiers validés') }} :
                                    {{ $vehicule->documents->where('status', 'approved')->count() }}
                                    / {{ $vehicule->documents->count() }}
                                </p>
                            </div>
                            <div class="flex flex-shrink-0 gap-2">
                                <a href="{{ route('peer.vehicule', $vehicule) }}" class="brio-btn-secondary !text-xs">{{ __('Voir') }}</a>
                                <button type="button" wire:click="publier({{ $vehicule->id }})" class="brio-btn-primary !text-xs">
                                    {{ __('Publier') }}
                                </button>
                                <button type="button" wire:click="refuserLAnnonce({{ $vehicule->id }})" class="brio-btn-danger !text-xs">
                                    {{ __('Refuser') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="✅" :title="__('Aucune annonce en attente')"
                                   :message="__('Toutes les annonces déposées ont été traitées.')" />
                @endforelse
            </div>

        @elseif ($onglet === 'logements')
            <div class="mb-4">
                <label for="recherche-logement" class="sr-only">{{ __('Chercher un logement') }}</label>
                <input id="recherche-logement" wire:model.live.debounce.400ms="rechercheLogement" type="search"
                       class="w-full" placeholder="{{ __('Titre, ville ou référence…') }}">
            </div>

            {{-- LA FILE D ATTENTE : chaque annonce se publie ou se refuse AVEC UN MOTIF. --}}
            <div class="space-y-2">
                @forelse ($this->logementsAVerifier as $logement)
                    <div class="brio-list-item !p-3" wire:key="verif-{{ $logement->id }}">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $logement->titre() }}</p>
                            <p class="text-xs opacity-70">
                                {{ collect([
                                    $logement->owner?->name,
                                    $logement->city,
                                    ucfirst($logement->property_type),
                                    $logement->max_guests . ' voyageur(s)',
                                    locale_currency($logement->nightly_price_cents / 100) . ' / nuit',
                                ])->filter()->join(' · ') }}
                            </p>
                            <p class="text-xs opacity-70">
                                {{ $logement->media->count() }} photo(s) · {{ $logement->reference }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <a href="{{ route('peer.sejour', $logement) }}" class="brio-btn-ligne !text-xs">
                                {{ __('Voir l’annonce') }}
                            </a>
                            <button type="button" wire:click="publierLeLogement({{ $logement->id }})"
                                    class="brio-btn-primary !px-3 !py-1.5 !text-xs">{{ __('Publier') }}</button>
                            <button type="button" wire:click="refuserLeLogement({{ $logement->id }})"
                                    class="brio-btn-ligne-danger !text-xs">{{ __('Refuser') }}</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm opacity-70">{{ __('Aucun logement n’attend de vérification.') }}</p>
                @endforelse

                <div>
                    <label for="motif-refus-logement" class="mb-1 block text-xs font-semibold">
                        {{ __('Motif du refus') }}
                        <span class="font-normal opacity-70">
                            {{ __('— un refus sans explication écrite n’est ni corrigeable, ni défendable') }}
                        </span>
                    </label>
                    <input id="motif-refus-logement" wire:model="motifRefus" type="text" class="w-full"
                           placeholder="{{ __('Photos illisibles, adresse absente…') }}">
                </div>
            </div>

            {{-- LES ANNONCES DEJA EN LIGNE : pouvoir en retirer une sans attendre un signalement. --}}
            <h3 class="mt-6 text-sm font-bold">{{ __('Logements en ligne') }}</h3>
            <div class="mt-2 space-y-2">
                @forelse ($this->logementsEnLigne as $logement)
                    <div class="brio-list-item !p-3" wire:key="ligne-{{ $logement->id }}">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $logement->titre() }}</p>
                            <p class="text-xs opacity-70">
                                {{ collect([
                                    $logement->owner?->name,
                                    $logement->city,
                                    $logement->sejours_count . ' séjour(s)',
                                ])->filter()->join(' · ') }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('peer.sejour', $logement) }}" class="brio-btn-ligne !text-xs">
                                {{ __('Voir') }}
                            </a>
                            <button type="button" wire:click="suspendreLeLogement({{ $logement->id }})"
                                    wire:confirm="{{ __('Retirer ce logement du catalogue ? Les séjours déjà réservés continuent.') }}"
                                    class="brio-btn-ligne-danger !text-xs">{{ __('Retirer') }}</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm opacity-70">{{ __('Aucun logement publié pour l’instant.') }}</p>
                @endforelse
            </div>

            {{-- CE QUI EST SORTI DU CATALOGUE. Sans cette liste, retirer une annonce etait
                 sans retour : plus aucun ecran ne la montrait. --}}
            <h3 class="mt-6 text-sm font-bold">{{ __('Refusés et retirés') }}</h3>
            <div class="mt-2 space-y-2">
                @forelse ($this->logementsHorsLigne as $logement)
                    <div class="brio-list-item !p-3" wire:key="hors-{{ $logement->id }}">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $logement->titre() }}</p>
                            <p class="text-xs opacity-70">
                                {{ collect([
                                    $logement->owner?->name,
                                    $logement->city,
                                    $logement->status === \App\Models\PeerStay::STATUT_REFUSE ? __('Refusé') : __('Retiré'),
                                    $logement->rejection_reason,
                                ])->filter()->join(' · ') }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" wire:click="publierLeLogement({{ $logement->id }})"
                                    class="brio-btn-primary !px-3 !py-1.5 !text-xs">{{ __('Remettre en ligne') }}</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm opacity-70">{{ __('Aucun logement refusé ni retiré.') }}</p>
                @endforelse
            </div>

        @elseif ($onglet === 'papiers')
            <div class="space-y-2">
                @forelse ($this->papiersAValider as $document)
                    <div class="brio-list-item flex items-center justify-between gap-3 !p-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ match ($document->document_type) {
                                    'registration' => __('Carte grise'),
                                    'insurance' => __('Assurance'),
                                    'technical_inspection' => __('Contrôle technique'),
                                    default => $document->document_type,
                                } }}
                                — {{ $document->vehicle?->titre() }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $document->vehicle?->owner?->name }}
                                @if ($document->expires_at) · {{ __('expire le') }} {{ $document->expires_at->format('d/m/Y') }} @endif
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 gap-2">
                            <button type="button" wire:click="validerLePapier({{ $document->id }})" class="brio-btn-primary !text-xs">
                                {{ __('Valider') }}
                            </button>
                            <button type="button" wire:click="refuserLePapier({{ $document->id }})" class="brio-btn-danger !text-xs">
                                {{ __('Refuser') }}
                            </button>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="📄" :title="__('Aucun papier en attente')"
                                   :message="__('Les cartes grises et attestations déposées ont toutes été traitées.')" />
                @endforelse
            </div>

        @else
            <div class="space-y-2">
                @forelse ($this->retenuesContestees as $retenue)
                    <div class="brio-list-item !p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ ucfirst($retenue->kind) }} —
                                    <x-money :amount="$retenue->amount_cents / 100" :currency="$retenue->rental?->currency" />
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $retenue->rental?->reference }} ·
                                    {{ $retenue->rental?->owner?->name }} → {{ $retenue->rental?->renter?->name }}
                                </p>
                                @if ($retenue->description)
                                    <p class="mt-1 text-sm text-slate-600">{{ $retenue->description }}</p>
                                @endif
                                @if ($retenue->resolution_note)
                                    <p class="mt-1 text-xs italic text-slate-500">
                                        {{ __('Contestation') }} : {{ $retenue->resolution_note }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex flex-shrink-0 items-center gap-2">
                                <input type="number" step="0.01" min="0" wire:model="montantArbitre"
                                       class="!w-28 !py-1.5 !text-xs" placeholder="{{ __('Montant €') }}"
                                       aria-label="{{ __('Montant accordé') }}">
                                <button type="button" wire:click="arbitrer({{ $retenue->id }})" class="brio-btn-primary !text-xs">
                                    {{ __('Arbitrer') }}
                                </button>
                            </div>
                        </div>

                        <a href="{{ route('peer.rental', $retenue->rental) }}"
                           class="mt-2 inline-block text-xs font-semibold text-sky-700 hover:underline">
                            {{ __('Voir la location et les états des lieux') }} →
                        </a>
                    </div>
                @empty
                    <x-empty-state icon="🤝" :title="__('Aucun litige')"
                                   :message="__('Les retenues sur caution se règlent entre les membres.')" />
                @endforelse
            </div>
        @endif
    </x-app-card>
</div>
