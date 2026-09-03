{{-- MES LOGEMENTS EN LOCATION. Une annonce nait en brouillon et n est visible de personne tant
     que son proprietaire ne l a pas envoyee en verification. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Location entre membres"
        title="Mes logements"
        subtitle="Publiez votre logement, fixez vos prix et vos règles, ouvrez votre calendrier.">
        <x-slot:actions>
            <span class="brio-inline-stat">{{ $this->logements->count() }} annonce(s)</span>
            <span class="brio-inline-stat">
                <x-money :amount="$this->revenusCents / 100" /> encaissés
            </span>
            <button type="button" wire:click="creer" class="brio-btn-primary">Mettre un logement en location</button>
        </x-slot:actions>
    </x-page-shell>

    @if($message)
        <div class="brio-alerte-success">{{ $message }}</div>
    @endif

    {{-- UN COMPTE SANS STRIPE CONNECT NE PEUT PAS ETRE PAYE. On le dit AVANT la premiere annonce :
         le decouvrir a l arrivee du voyageur, une fois les cles remises, serait le pire moment. --}}
    @unless($this->peutEtrePaye)
        <div class="brio-alerte-warning">
            <p class="font-semibold">Vous ne pouvez pas encore être payé.</p>
            <p class="mt-1 text-sm">
                Terminez votre inscription au paiement pour recevoir vos revenus. Vous pouvez
                préparer vos annonces dès maintenant : elles ne partiront pas en ligne sans cela.
            </p>
            @if(Route::has('employe.stripe-connect.start'))
                <a href="{{ route('employe.stripe-connect.start') }}" class="brio-btn-ligne mt-3 inline-flex">
                    Terminer mon inscription au paiement
                </a>
            @endif
        </div>
    @endunless

    @if($this->note !== null)
        <p class="text-sm opacity-70">
            Votre note de propriétaire : <span class="font-semibold">{{ number_format($this->note, 1, ',', ' ') }}/5</span>
        </p>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($this->logements as $logement)
            <a href="{{ route('peer.owner.stay', $logement) }}" wire:navigate
               class="brio-card flex flex-col gap-3 overflow-hidden p-0 transition hover:opacity-90"
               wire:key="logement-{{ $logement->id }}">

                @if($logement->photoPrincipale())
                    <img src="{{ Storage::url($logement->photoPrincipale()->path) }}"
                         alt="" class="h-44 w-full object-cover">
                @else
                    <div class="flex h-44 w-full items-center justify-center bg-slate-100 text-4xl dark:bg-slate-800">
                        🏠
                    </div>
                @endif

                <div class="space-y-2 p-4 pt-0">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold">{{ $logement->title ?: 'Annonce sans titre' }}</span>
                        <x-badge :status="$logement->status" />
                    </div>

                    <p class="text-sm opacity-70">
                        {{ collect([$logement->city, $logement->property_type])->filter()->join(' · ') ?: 'Adresse à compléter' }}
                    </p>

                    <p class="text-sm">
                        <span class="font-semibold"><x-money :amount="$logement->nightly_price_cents / 100" /></span>
                        <span class="opacity-70"> / nuit · {{ $logement->locations_count }} séjour(s)</span>
                    </p>
                </div>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-empty-state icon="🏠" title="Aucun logement"
                               message="Mettez votre premier logement en location : vous fixez le prix, les règles et les dates." />
            </div>
        @endforelse
    </div>
</div>
