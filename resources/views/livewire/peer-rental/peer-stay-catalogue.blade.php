{{-- LE CATALOGUE DES LOGEMENTS. Les dates filtrent VRAIMENT : une annonce deja prise sur la
     periode demandee ne s affiche pas, plutot que d etre refusee a la reservation. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Location entre membres"
        title="Louer un logement"
        subtitle="Des logements proposés par les membres de la plateforme, du studio à la maison entière.">
        <x-slot:actions>
            <span class="brio-inline-stat">{{ $this->logements->count() }} logement(s)</span>
            <a href="{{ route('peer.catalogue') }}" wire:navigate class="brio-btn-ligne">Louer un véhicule</a>
        </x-slot:actions>
    </x-page-shell>

    <x-filter-panel title="Votre recherche" subtitle="Les dates écartent les logements déjà réservés.">
        <div class="brio-filter-grid">
            <input wire:model.live.debounce.400ms="lieu" type="text" aria-label="Ville ou code postal"
                   placeholder="Ville ou code postal">

            <div>
                <label for="c-debut" class="mb-1 block text-xs font-semibold">Arrivée</label>
                <input id="c-debut" wire:model.live="debut" type="date" class="w-full">
            </div>

            <div>
                <label for="c-fin" class="mb-1 block text-xs font-semibold">Départ</label>
                <input id="c-fin" wire:model.live="fin" type="date" class="w-full">
            </div>

            <div>
                <label for="c-voyageurs" class="mb-1 block text-xs font-semibold">Voyageurs</label>
                <input id="c-voyageurs" wire:model.live="voyageurs" type="number" min="1" class="w-full">
            </div>
        </div>

        <div class="brio-filter-grid mt-3">
            <select wire:model.live="type" aria-label="Type de bien">
                <option value="">Tous les types</option>
                @foreach(\App\Models\PeerStay::TYPES as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select>

            <select wire:model.live="espace" aria-label="Type d’espace">
                <option value="">Entier ou partagé</option>
                <option value="entire">Logement entier</option>
                <option value="private_room">Chambre privée</option>
                <option value="shared_room">Chambre partagée</option>
            </select>

            <input wire:model.live.debounce.400ms="prixMax" type="number" min="0"
                   aria-label="Prix maximum par nuit" placeholder="Prix max / nuit (€)">

            <select wire:model.live="tri" aria-label="Trier">
                <option value="recent">Les plus récents</option>
                <option value="prix">Prix croissant</option>
                <option value="prix_desc">Prix décroissant</option>
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.400ms="chambresMin" type="number" min="0" class="max-w-[10rem]"
                   aria-label="Chambres minimum" placeholder="Chambres min.">

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="reservationImmediate">
                Réservation immédiate
            </label>

            <button type="button" wire:click="reinitialiserLesFiltres" class="brio-btn-ligne">Réinitialiser</button>
        </div>

        <details class="mt-3">
            <summary class="cursor-pointer text-sm font-semibold">Équipements</summary>
            <div class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-4">
                @foreach(\App\Livewire\PeerRental\PeerStayEditor::EQUIPEMENTS as $cle => $libelle)
                    <label class="flex items-center gap-2 text-sm" wire:key="f-{{ $cle }}">
                        <input type="checkbox" wire:model.live="equipements" value="{{ $cle }}">
                        {{ $libelle }}
                    </label>
                @endforeach
            </div>
        </details>
    </x-filter-panel>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($this->logements as $logement)
            <a href="{{ route('peer.sejour', $logement) }}" wire:navigate
               class="brio-card flex flex-col overflow-hidden p-0 transition hover:opacity-90"
               wire:key="cat-{{ $logement->id }}">

                @if($logement->photoPrincipale())
                    <img src="{{ Storage::url($logement->photoPrincipale()->path) }}"
                         alt="" class="h-48 w-full object-cover">
                @else
                    <div class="flex h-48 w-full items-center justify-center bg-slate-100 text-4xl dark:bg-slate-800">
                        🏠
                    </div>
                @endif

                <div class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold">{{ $logement->title }}</span>
                        @if($logement->instant_booking)
                            <span class="brio-chip shrink-0">Immédiat</span>
                        @endif
                    </div>

                    <p class="text-sm opacity-70">
                        {{ collect([
                            $logement->city,
                            ucfirst($logement->property_type),
                            $logement->max_guests . ' voyageur(s)',
                            $logement->bedrooms . ' chambre(s)',
                        ])->filter()->join(' · ') }}
                    </p>

                    <p class="text-sm">
                        <span class="font-semibold">
                            <x-money :amount="$logement->nightly_price_cents / 100" :currency="$logement->currency" />
                        </span>
                        <span class="opacity-70"> / nuit</span>
                        @if($logement->cleaning_fee_cents > 0)
                            <span class="block text-xs opacity-70">
                                + <x-money :amount="$logement->cleaning_fee_cents / 100" :currency="$logement->currency" /> de ménage
                            </span>
                        @endif
                    </p>
                </div>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-empty-state icon="🏠" title="Aucun logement"
                               message="Aucun logement ne correspond à cette recherche. Élargissez vos dates ou vos filtres." />
            </div>
        @endforelse
    </div>
</div>
