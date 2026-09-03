{{-- L ANNONCE D UN LOGEMENT. Le proprietaire decrit, tarife, photographie, ouvre son calendrier,
     puis DEMANDE la publication : il ne publie pas lui-meme. --}}
<div class="space-y-6">
    @php($logement = $this->logement)

    <x-page-shell
        eyebrow="Location entre membres"
        :title="$logement->title ?: 'Nouvelle annonce'"
        subtitle="Décrivez, tarifez, photographiez, puis envoyez en vérification.">
        <x-slot:actions>
            <x-badge :status="$logement->status" />
            <span class="brio-inline-stat">{{ $logement->reference }}</span>
            <a href="{{ route('peer.owner.stays') }}" wire:navigate class="brio-btn-ligne">Mes logements</a>
        </x-slot:actions>
    </x-page-shell>

    @if($message)  <div class="brio-alerte-success">{{ $message }}</div> @endif
    @if($erreur)   <div class="brio-alerte-danger">{{ $erreur }}</div>   @endif

    {{-- CE QUI MANQUE POUR PUBLIER, DIT A L AVANCE. Une annonce refusee apres coup coute au
         proprietaire une attente et une deception. --}}
    @if($this->motifsDeBlocage !== [])
        <x-app-card title="Il reste à compléter" subtitle="Votre annonce ne peut pas partir en vérification tant que ces points manquent.">
            <ul class="space-y-1 text-sm">
                @foreach($this->motifsDeBlocage as $motif)
                    <li>• {{ $motif }}</li>
                @endforeach
            </ul>
        </x-app-card>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">

            {{-- ── Description ──────────────────────────────────────────── --}}
            <x-app-card title="Le logement" subtitle="Ce que le voyageur lit en premier.">
                <form wire:submit="enregistrer" class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="s-titre" class="mb-1 block text-sm font-semibold">Titre</label>
                            <input id="s-titre" wire:model="titre" type="text" class="w-full"
                                   placeholder="Studio lumineux à deux pas du centre">
                            @error('titre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="s-description" class="mb-1 block text-sm font-semibold">Description</label>
                            <textarea id="s-description" wire:model="description" rows="4" class="w-full"></textarea>
                            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="s-type" class="mb-1 block text-sm font-semibold">Type de bien</label>
                            <select id="s-type" wire:model="typeDeBien" class="w-full">
                                @foreach(\App\Models\PeerStay::TYPES as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="s-espace" class="mb-1 block text-sm font-semibold">
                                Ce que le voyageur occupe
                            </label>
                            <select id="s-espace" wire:model="typeDEspace" class="w-full">
                                <option value="entire">Tout le logement</option>
                                <option value="private_room">Une chambre privée</option>
                                <option value="shared_room">Une chambre partagée</option>
                            </select>
                        </div>

                        <div>
                            <label for="s-voyageurs" class="mb-1 block text-sm font-semibold">Voyageurs maximum</label>
                            <input id="s-voyageurs" wire:model="voyageursMax" type="number" min="1" class="w-full">
                            @error('voyageursMax') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="s-surface" class="mb-1 block text-sm font-semibold">Surface (m²)</label>
                            <input id="s-surface" wire:model="surface" type="number" min="1" class="w-full">
                        </div>

                        <div>
                            <label for="s-chambres" class="mb-1 block text-sm font-semibold">Chambres</label>
                            <input id="s-chambres" wire:model="chambres" type="number" min="0" class="w-full">
                        </div>

                        <div>
                            <label for="s-lits" class="mb-1 block text-sm font-semibold">Lits</label>
                            <input id="s-lits" wire:model="lits" type="number" min="0" class="w-full">
                        </div>

                        <div>
                            <label for="s-bains" class="mb-1 block text-sm font-semibold">
                                Salles de bain <span class="font-normal opacity-70">— 0,5 pour un WC séparé</span>
                            </label>
                            <input id="s-bains" wire:model="sallesDeBain" type="number" step="0.5" min="0" class="w-full">
                        </div>
                    </div>

                    <fieldset>
                        <legend class="mb-2 text-sm font-bold">Équipements</legend>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                            @foreach(\App\Livewire\PeerRental\PeerStayEditor::EQUIPEMENTS as $cle => $libelle)
                                <label class="flex items-center gap-2 text-sm" wire:key="eq-{{ $cle }}">
                                    <input type="checkbox" wire:model="equipements" value="{{ $cle }}">
                                    {{ $libelle }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label for="s-reglement" class="mb-1 block text-sm font-semibold">Règlement intérieur</label>
                        <textarea id="s-reglement" wire:model="reglement" rows="3" class="w-full"
                                  placeholder="Non-fumeur, pas de fête après 22h…"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="brio-btn-primary">Enregistrer</button>
                    </div>
                </form>
            </x-app-card>

            {{-- ── Prix ─────────────────────────────────────────────────── --}}
            <x-app-card title="Prix" subtitle="Le prix affiché au voyageur se calcule à partir d’ici, nuit par nuit.">
                {{-- CE QUE LA PLATEFORME PRELEVE, ICI ET MAINTENANT. La note interroge le
                     resolveur a chaque rendu : recopier un chiffre ferait mentir la page le
                     jour ou le taux change. --}}
                <x-note-commission class="mb-4"
                    :module="\App\Models\CommissionRule::MODULE_LOCATION_MEMBRES"
                    type-de-bien="stay"
                    :montant-cents="$logement->nightly_price_cents ?: 9000" />

                <form wire:submit="enregistrer" class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="s-prix" class="mb-1 block text-sm font-semibold">Prix par nuit (centimes)</label>
                        <input id="s-prix" wire:model="prixParNuit" type="number" min="100" class="w-full">
                        @error('prixParNuit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="s-menage" class="mb-1 block text-sm font-semibold">
                            Frais de ménage <span class="font-normal opacity-70">— facturés une fois</span>
                        </label>
                        <input id="s-menage" wire:model="fraisDeMenage" type="number" min="0" class="w-full">
                    </div>

                    <div>
                        <label for="s-inclus" class="mb-1 block text-sm font-semibold">Voyageurs inclus dans le prix</label>
                        <input id="s-inclus" wire:model="voyageursInclus" type="number" min="1" class="w-full">
                    </div>

                    <div>
                        <label for="s-supplement" class="mb-1 block text-sm font-semibold">
                            Par voyageur en plus <span class="font-normal opacity-70">— et par nuit</span>
                        </label>
                        <input id="s-supplement" wire:model="prixVoyageurEnPlus" type="number" min="0" class="w-full">
                    </div>

                    <div>
                        <label for="s-caution" class="mb-1 block text-sm font-semibold">Caution</label>
                        <input id="s-caution" wire:model="caution" type="number" min="0" class="w-full">
                    </div>

                    <div>
                        <label for="s-r3" class="mb-1 block text-sm font-semibold">Remise dès 3 nuits (%)</label>
                        <input id="s-r3" wire:model="remise3" type="number" min="0" max="90" class="w-full">
                    </div>

                    <div>
                        <label for="s-r7" class="mb-1 block text-sm font-semibold">Remise dès 7 nuits (%)</label>
                        <input id="s-r7" wire:model="remise7" type="number" min="0" max="90" class="w-full">
                    </div>

                    <div>
                        <label for="s-r28" class="mb-1 block text-sm font-semibold">Remise dès 28 nuits (%)</label>
                        <input id="s-r28" wire:model="remise28" type="number" min="0" max="90" class="w-full">
                    </div>

                    {{-- LE PRIX DE SAISON. Ces majorations s appliquaient deja, en silence :
                         elles se voient et se reglent desormais annonce par annonce. --}}
                    <div class="md:col-span-2">
                        <h3 class="text-sm font-bold">Prix de saison</h3>
                        <p class="text-xs opacity-70">
                            La majoration la plus forte l’emporte : un samedi de haute saison n’est majoré qu’une fois.
                        </p>
                    </div>

                    <div>
                        <label for="s-we" class="mb-1 block text-sm font-semibold">Majoration week-end (%)</label>
                        <input id="s-we" wire:model="majorationWeekend" type="number" min="0" max="300" class="w-full">
                    </div>

                    <div>
                        <label for="s-hs" class="mb-1 block text-sm font-semibold">Majoration haute saison (%)</label>
                        <input id="s-hs" wire:model="majorationHauteSaison" type="number" min="0" max="300" class="w-full">
                    </div>

                    <fieldset class="md:col-span-2">
                        <legend class="mb-1 text-sm font-semibold">Mois de haute saison</legend>
                        <div class="grid grid-cols-3 gap-2 md:grid-cols-6">
                            @foreach ([1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
                                       7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'] as $numero => $mois)
                                <label class="flex items-center gap-2 text-sm" wire:key="mois-{{ $numero }}">
                                    <input type="checkbox" wire:model="moisHauteSaison" value="{{ $numero }}">
                                    {{ $mois }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex justify-end md:col-span-2">
                        <button type="submit" class="brio-btn-primary">Enregistrer</button>
                    </div>
                </form>
            </x-app-card>

            {{-- ── Séjour et adresse ────────────────────────────────────── --}}
            <x-app-card title="Séjour et adresse" subtitle="Vos règles d’arrivée, vos durées, et où se trouve le logement.">
                <form wire:submit="enregistrer" class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="s-min" class="mb-1 block text-sm font-semibold">Nuits minimum</label>
                        <input id="s-min" wire:model="nuitsMin" type="number" min="1" class="w-full">
                    </div>

                    <div>
                        <label for="s-max" class="mb-1 block text-sm font-semibold">Nuits maximum</label>
                        <input id="s-max" wire:model="nuitsMax" type="number" min="1" class="w-full">
                    </div>

                    <div>
                        <label for="s-arrivee" class="mb-1 block text-sm font-semibold">Arrivée à partir de</label>
                        <input id="s-arrivee" wire:model="arriveeApres" type="time" class="w-full">
                    </div>

                    <div>
                        <label for="s-depart" class="mb-1 block text-sm font-semibold">Départ avant</label>
                        <input id="s-depart" wire:model="departAvant" type="time" class="w-full">
                    </div>

                    <div class="md:col-span-2">
                        <label for="s-adresse" class="mb-1 block text-sm font-semibold">Adresse</label>
                        <input id="s-adresse" wire:model="adresse" type="text" class="w-full">
                    </div>

                    <div>
                        <label for="s-cp" class="mb-1 block text-sm font-semibold">Code postal</label>
                        <input id="s-cp" wire:model="codePostal" type="text" class="w-full">
                    </div>

                    <div>
                        <label for="s-ville" class="mb-1 block text-sm font-semibold">Ville</label>
                        <input id="s-ville" wire:model="ville" type="text" class="w-full">
                    </div>

                    <div>
                        <label for="s-pays" class="mb-1 block text-sm font-semibold">Pays</label>
                        <input id="s-pays" wire:model="pays" type="text" maxlength="2" class="w-full uppercase">
                    </div>

                    <div>
                        <label for="s-annulation" class="mb-1 block text-sm font-semibold">Politique d’annulation</label>
                        <select id="s-annulation" wire:model="politiqueDAnnulation" class="w-full">
                            <option value="souple">Souple</option>
                            <option value="moderee">Modérée</option>
                            <option value="stricte">Stricte</option>
                        </select>
                    </div>

                    <label class="flex items-start gap-2 text-sm md:col-span-2">
                        <input type="checkbox" wire:model="reservationInstantanee" class="mt-0.5">
                        <span>
                            Réservation instantanée
                            <span class="block text-xs opacity-70">
                                Le voyageur réserve sans attendre votre réponse. Vos dates fermées restent respectées.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end md:col-span-2">
                        <button type="submit" class="brio-btn-primary">Enregistrer</button>
                    </div>
                </form>
            </x-app-card>
        </div>

        {{-- ── Colonne de droite ────────────────────────────────────────── --}}
        <div class="space-y-4">
            <x-app-card title="Photos" subtitle="La première photo est la couverture de votre annonce.">
                <div class="grid grid-cols-2 gap-2">
                    @forelse($logement->media as $photo)
                        <div class="space-y-1" wire:key="photo-{{ $photo->id }}">
                            <img src="{{ Storage::url($photo->path) }}" alt=""
                                 class="h-24 w-full rounded-xl object-cover">
                            <div class="flex gap-1">
                                <button type="button" wire:click="definirLaCouverture({{ $photo->id }})"
                                        class="brio-btn-ligne flex-1 text-xs">Couverture</button>
                                <button type="button" wire:click="supprimerUnePhoto({{ $photo->id }})"
                                        class="brio-btn-ligne-danger text-xs" aria-label="Retirer cette photo">&times;</button>
                            </div>
                        </div>
                    @empty
                        <p class="col-span-2 text-sm opacity-70">Aucune photo pour l’instant.</p>
                    @endforelse
                </div>

                <form wire:submit="ajouterDesPhotos" class="mt-3 space-y-2">
                    <label for="s-photos" class="mb-1 block text-sm font-semibold">Ajouter des photos</label>
                    <input id="s-photos" type="file" wire:model="photos" multiple accept="image/*" class="w-full">
                    @error('photos.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="brio-btn-ligne w-full">Téléverser</button>
                </form>
            </x-app-card>

            {{-- LES PAPIERS. Le fichier part sur le disque PRIVE : un titre de propriete porte
                 un nom, une adresse et parfois un numero national. --}}
            <x-app-card title="Papiers" subtitle="L’assurance et le titre sont vérifiés avant la mise en ligne.">
                <div class="space-y-2">
                    @forelse($logement->documents as $papier)
                        <div class="brio-list-item flex items-center justify-between gap-3 !p-3"
                             wire:key="papier-{{ $papier->id }}">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ $papier->libelle() }}</p>
                                <p class="text-xs opacity-70">
                                    {{ collect([
                                        $papier->file_name,
                                        $papier->expires_at ? "expire le " . $papier->expires_at->format('d/m/Y') : null,
                                        $papier->rejection_reason,
                                    ])->filter()->join(' · ') }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <a href="{{ route('peer.document', $papier) }}" target="_blank" rel="noopener"
                                   class="brio-btn-ligne text-xs">Ouvrir</a>
                                @php($tons = [
                                    \App\Models\PeerVehicleDocument::STATUT_VALIDE => 'success',
                                    \App\Models\PeerVehicleDocument::STATUT_REFUSE => 'danger',
                                ])
                                <x-ui.badge :tone="$tons[$papier->status] ?? 'warning'"
                                            :label="match($papier->status) {
                                                \App\Models\PeerVehicleDocument::STATUT_VALIDE => __('Validé'),
                                                \App\Models\PeerVehicleDocument::STATUT_REFUSE => __('Refusé'),
                                                default => __('En vérification'),
                                            }" />

                                @if($papier->status !== \App\Models\PeerVehicleDocument::STATUT_VALIDE)
                                    <button type="button" wire:click="supprimerUnDocument({{ $papier->id }})"
                                            class="brio-btn-ligne-danger text-xs"
                                            aria-label="Retirer ce document">&times;</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm opacity-70">Aucun papier déposé pour l’instant.</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-2 border-t border-slate-200/60 pt-4 dark:border-slate-700">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="s-type-doc" class="mb-1 block text-sm font-semibold">Type</label>
                            <select id="s-type-doc" wire:model="typeDocument" class="w-full">
                                @foreach(\App\Livewire\PeerRental\PeerStayEditor::DOCUMENTS as $type)
                                    <option value="{{ $type }}">
                                        {{ \App\Models\PeerVehicleDocument::LIBELLES[$type] ?? $type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="s-exp-doc" class="mb-1 block text-sm font-semibold">Expire le</label>
                            <input id="s-exp-doc" wire:model="expirationDocument" type="date" class="w-full">
                        </div>
                    </div>

                    <div>
                        <label for="s-fichier-doc" class="mb-1 block text-sm font-semibold">Fichier</label>
                        <input id="s-fichier-doc" type="file" accept=".pdf,image/*" wire:model="fichierDocument"
                               class="w-full">
                        @error('fichierDocument') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="button" wire:click="deposerUnDocument" class="brio-btn-ligne w-full">Déposer</button>
                </div>
            </x-app-card>

            <x-app-card title="Calendrier" subtitle="Fermez les périodes où le logement n’est pas disponible.">
                @forelse($logement->indisponibilites as $periode)
                    <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 py-2 text-sm last:border-0 dark:border-slate-700"
                         wire:key="periode-{{ $periode->id }}">
                        <span>
                            {{ $periode->starts_on?->format('d/m/Y') }} → {{ $periode->ends_on?->format('d/m/Y') }}
                            @if($periode->reason)
                                <span class="block text-xs opacity-70">{{ $periode->reason }}</span>
                            @endif
                        </span>
                        <button type="button" wire:click="rouvrirUnePeriode({{ $periode->id }})"
                                class="brio-btn-ligne text-xs">Rouvrir</button>
                    </div>
                @empty
                    <p class="text-sm opacity-70">Aucune période fermée : le logement est disponible partout ailleurs.</p>
                @endforelse

                <form wire:submit="fermerUnePeriode" class="mt-3 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="s-fdebut" class="mb-1 block text-xs font-semibold">Du</label>
                            <input id="s-fdebut" wire:model="fermetureDebut" type="date" class="w-full">
                        </div>
                        <div>
                            <label for="s-ffin" class="mb-1 block text-xs font-semibold">Au</label>
                            <input id="s-ffin" wire:model="fermetureFin" type="date" class="w-full">
                        </div>
                    </div>
                    @error('fermetureDebut') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('fermetureFin') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <input wire:model="fermetureMotif" type="text" class="w-full"
                           aria-label="Motif" placeholder="Motif (facultatif)">

                    <button type="submit" class="brio-btn-ligne w-full">Fermer cette période</button>
                </form>
            </x-app-card>

            <x-app-card title="Mise en ligne" subtitle="Une annonce part en vérification avant d’être visible.">
                @if($logement->status === \App\Models\PeerStay::STATUT_PUBLIE)
                    <a href="{{ route('peer.sejour', $logement) }}" class="brio-btn-ligne mb-2 block text-center">
                        Voir mon annonce
                    </a>
                    <button type="button" wire:click="mettreEnPause" class="brio-btn-ligne-danger w-full">
                        Mettre en pause
                    </button>
                    <p class="mt-2 text-xs opacity-70">Les séjours déjà réservés continuent.</p>
                @elseif($logement->status === \App\Models\PeerStay::STATUT_SUSPENDU)
                    <button type="button" wire:click="reprendre" class="brio-btn-primary w-full">
                        Remettre en ligne
                    </button>
                @elseif($logement->status === \App\Models\PeerStay::STATUT_EN_REVUE)
                    <p class="text-sm">Votre annonce est en cours de vérification.</p>
                @else
                    <button type="button" wire:click="demanderLaPublication" class="brio-btn-primary w-full">
                        Envoyer en vérification
                    </button>

                    @if($logement->rejection_reason)
                        <p class="mt-2 text-sm text-red-600">
                            <span class="font-semibold">Refus précédent :</span> {{ $logement->rejection_reason }}
                        </p>
                    @endif
                @endif
            </x-app-card>
        </div>
    </div>
</div>
