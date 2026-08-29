<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Administration')"
        :title="__('Location entre membres')"
        :subtitle="__('Les annonces à vérifier, les papiers à valider, les retenues contestées.')" />

    @if ($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif
    @if ($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.stat :title="__('Véhicules en ligne')" :value="$this->chiffres['vehicules']" icon="🚗" tone="blue" />
        <x-ui.stat :title="__('Locations en cours')" :value="$this->chiffres['locations_en_cours']" icon="🔑" tone="emerald" />
        <x-ui.stat :title="__('Commission encaissée')"
                   :value="locale_currency($this->chiffres['commission_cents'] / 100)" icon="💶" tone="emerald" />
        <x-ui.stat :title="__('Litiges')" :value="$this->chiffres['litiges']" icon="⚠️" tone="red" />
    </div>

    <x-app-card>
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'annonces' => __('Annonces') . ' (' . $this->annoncesAVerifier->count() . ')',
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
