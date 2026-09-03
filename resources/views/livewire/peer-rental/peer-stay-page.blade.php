{{-- L ANNONCE VUE PAR LE VOYAGEUR. Le total REEL — menage et voyageurs compris — s affiche AVANT
     toute saisie de moyen de paiement : un prix qui apparait a la derniere etape fait fuir. --}}
<div class="space-y-6">
    @php($logement = $this->logement)

    <x-page-shell
        eyebrow="Location entre membres"
        :title="$logement->title"
        :subtitle="collect([$logement->city, $logement->country_code])->filter()->join(', ')">
        <x-slot:actions>
            <span class="brio-inline-stat">
                <x-money :amount="$logement->nightly_price_cents / 100" /> / nuit
            </span>
            @if($this->noteDuProprietaire !== null)
                <span class="brio-inline-stat">
                    Hôte {{ number_format($this->noteDuProprietaire, 1, ',', ' ') }}/5
                </span>
            @endif
        </x-slot:actions>
    </x-page-shell>

    @unless($logement->estPubliable())
        <div class="brio-alerte-warning">
            Cette annonce n’est pas encore publiée. Vous la voyez parce qu’elle vous appartient.
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">

            @if($logement->media->isNotEmpty())
                <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                    @foreach($logement->media as $photo)
                        <img src="{{ Storage::url($photo->path) }}" alt="{{ $photo->caption }}"
                             class="h-40 w-full rounded-2xl object-cover" wire:key="p-{{ $photo->id }}">
                    @endforeach
                </div>
            @endif

            <x-app-card title="Le logement">
                <div class="flex flex-wrap gap-2">
                    <span class="brio-chip">{{ ucfirst($logement->property_type) }}</span>
                    <span class="brio-chip">
                        {{ ['entire' => 'Logement entier', 'private_room' => 'Chambre privée',
                            'shared_room' => 'Chambre partagée'][$logement->space_type] ?? $logement->space_type }}
                    </span>
                    <span class="brio-chip">{{ $logement->max_guests }} voyageur(s)</span>
                    <span class="brio-chip">{{ $logement->bedrooms }} chambre(s)</span>
                    <span class="brio-chip">{{ $logement->beds }} lit(s)</span>
                    <span class="brio-chip">{{ rtrim(rtrim((string) $logement->bathrooms, '0'), '.') }} salle(s) de bain</span>
                    @if($logement->surface_m2)
                        <span class="brio-chip">{{ $logement->surface_m2 }} m²</span>
                    @endif
                </div>

                @if($logement->description)
                    <p class="mt-4 whitespace-pre-line text-sm leading-relaxed">{{ $logement->description }}</p>
                @endif

                @if($logement->equipements() !== [])
                    <h3 class="mt-5 text-sm font-bold">Équipements</h3>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($logement->equipements() as $equipement)
                            <span class="brio-chip">
                                {{ \App\Livewire\PeerRental\PeerStayEditor::EQUIPEMENTS[$equipement] ?? $equipement }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if($logement->house_rules)
                    <h3 class="mt-5 text-sm font-bold">Règlement intérieur</h3>
                    <p class="mt-1 whitespace-pre-line text-sm opacity-80">{{ $logement->house_rules }}</p>
                @endif

                @if($logement->check_in_from || $logement->check_out_before)
                    <p class="mt-4 text-sm opacity-70">
                        @if($logement->check_in_from) Arrivée à partir de {{ substr((string) $logement->check_in_from, 0, 5) }}. @endif
                        @if($logement->check_out_before) Départ avant {{ substr((string) $logement->check_out_before, 0, 5) }}. @endif
                    </p>
                @endif
            </x-app-card>
        </div>

        {{-- ── La réservation ───────────────────────────────────────────── --}}
        <div>
            <x-app-card title="Réserver" subtitle="Les fonds sont bloqués, rien n’est encaissé avant votre arrivée.">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="r-debut" class="mb-1 block text-xs font-semibold">Arrivée</label>
                            <input id="r-debut" wire:model.live="debut" type="date" class="w-full">
                        </div>
                        <div>
                            <label for="r-fin" class="mb-1 block text-xs font-semibold">Départ</label>
                            <input id="r-fin" wire:model.live="fin" type="date" class="w-full">
                        </div>
                    </div>

                    <div>
                        <label for="r-voyageurs" class="mb-1 block text-xs font-semibold">Voyageurs</label>
                        <input id="r-voyageurs" wire:model.live="voyageurs" type="number"
                               min="1" max="{{ $logement->max_guests }}" class="w-full">
                    </div>

                    @if($this->indisponibilite)
                        <div class="brio-alerte-warning text-sm">{{ $this->indisponibilite }}</div>
                    @endif

                    {{-- LE TOTAL REEL, LIGNE PAR LIGNE. C est ce que le voyageur paiera, a l euro
                         pres : rien n apparait plus tard. --}}
                    @if($this->devis)
                        @php($devis = $this->devis)

                        <div class="space-y-1 rounded-2xl border border-slate-200/80 p-3 text-sm dark:border-slate-700">
                            <div class="flex justify-between">
                                <span><x-money :amount="$devis['daily_price_cents'] / 100" /> × {{ $devis['days'] }} nuit(s)</span>
                                <span class="tabular-nums"><x-money :amount="$devis['subtotal_cents'] / 100" /></span>
                            </div>

                            @if($devis['discount_cents'] > 0)
                                <div class="flex justify-between text-emerald-600">
                                    <span>Remise séjour ({{ $devis['discount_percent'] }} %)</span>
                                    <span class="tabular-nums">− <x-money :amount="$devis['discount_cents'] / 100" /></span>
                                </div>
                            @endif

                            @foreach($devis['supplements'] as $libelle => $cents)
                                <div class="flex justify-between">
                                    <span>{{ ['menage' => 'Frais de ménage', 'voyageurs' => 'Voyageurs supplémentaires'][$libelle] ?? ucfirst($libelle) }}</span>
                                    <span class="tabular-nums"><x-money :amount="$cents / 100" /></span>
                                </div>
                            @endforeach

                            <div class="mt-2 flex justify-between border-t border-slate-200/80 pt-2 font-bold dark:border-slate-700">
                                <span>Total</span>
                                <span class="tabular-nums"><x-money :amount="$devis['total_cents'] / 100" /></span>
                            </div>

                            @if($devis['deposit_cents'] > 0)
                                <p class="text-xs opacity-70">
                                    Caution de <x-money :amount="$devis['deposit_cents'] / 100" /> bloquée, puis libérée après votre départ.
                                </p>
                            @endif
                        </div>
                    @endif

                    <div>
                        <label for="r-paiement" class="mb-1 block text-xs font-semibold">Moyen de paiement</label>
                        <input id="r-paiement" wire:model="paymentMethodId" type="text" class="w-full"
                               placeholder="pm_…">
                    </div>

                    @if($erreur)
                        <div class="brio-alerte-danger text-sm">{{ $erreur }}</div>
                    @endif

                    <button type="button" wire:click="reserver" class="brio-btn-primary w-full"
                            @disabled($this->devis === null || $this->indisponibilite !== null)>
                        {{ $logement->instant_booking ? 'Réserver' : 'Demander à réserver' }}
                    </button>

                    <p class="text-xs opacity-70">
                        @if($logement->instant_booking)
                            Ce logement accepte les réservations immédiates.
                        @else
                            L’hôte a 24 h pour répondre. Vos fonds sont libérés s’il ne répond pas.
                        @endif
                    </p>
                </div>
            </x-app-card>
        </div>
    </div>
</div>
