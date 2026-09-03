<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerStay;
use App\Models\PeerStayMedium;
use App\Models\PeerVehicleAvailability;
use App\Models\PeerVehicleDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * L'ANNONCE D'UN LOGEMENT, DE BOUT EN BOUT.
 *
 * Le propriétaire décrit, tarife, photographie, ouvre son calendrier, puis DEMANDE la publication.
 * Il ne publie pas lui-même : une annonce entre en vérification, et c'est l'administration qui
 * ouvre la porte — comme pour les véhicules.
 *
 * @property-read PeerStay $logement
 * @property-read list<string> $motifsDeBlocage
 */
#[Layout('layouts.app')]
class PeerStayEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $stayId;

    public ?string $message = null;

    public ?string $erreur = null;

    // ── Description ────────────────────────────────────────────────────────
    public string $titre = '';

    public string $description = '';

    public string $typeDeBien = 'appartement';

    public string $typeDEspace = 'entire';

    public int $voyageursMax = 2;

    public int $chambres = 1;

    public int $lits = 1;

    public string $sallesDeBain = '1';

    public ?int $surface = null;

    /** @var list<string> */
    public array $equipements = [];

    public string $reglement = '';

    // ── Prix ───────────────────────────────────────────────────────────────
    public int $prixParNuit = 6000;

    public int $fraisDeMenage = 0;

    public int $voyageursInclus = 1;

    public int $prixVoyageurEnPlus = 0;

    public int $remise3 = 0;

    public int $remise7 = 0;

    public int $remise28 = 0;

    public int $caution = 0;

    // ── Séjour ─────────────────────────────────────────────────────────────
    public int $nuitsMin = 1;

    public int $nuitsMax = 90;

    public string $arriveeApres = '';

    public string $departAvant = '';

    public bool $reservationInstantanee = false;

    /** Majoration du week-end, en pourcentage du prix d une nuit. */
    public int $majorationWeekend = 0;

    public int $majorationHauteSaison = 0;

    /** @var list<int> Les mois de haute saison, de 1 a 12. */
    public array $moisHauteSaison = [];

    // ── Papiers ────────────────────────────────────────────────────────────
    public mixed $fichierDocument = null;

    public string $typeDocument = PeerVehicleDocument::TYPE_ASSURANCE;

    public string $expirationDocument = '';

    public string $politiqueDAnnulation = 'moderee';

    // ── Où ─────────────────────────────────────────────────────────────────
    public string $adresse = '';

    public string $codePostal = '';

    public string $ville = '';

    public string $pays = 'BE';

    // ── Calendrier et photos ───────────────────────────────────────────────
    public string $fermetureDebut = '';

    public string $fermetureFin = '';

    public string $fermetureMotif = '';

    /** @var array<int, mixed> */
    public array $photos = [];

    /** Les équipements qu'une annonce peut cocher, dans l'ordre où on les cherche. */
    /**
     * LES PAPIERS QU'UN LOGEMENT PEUT DEPOSER.
     *
     * Les deux premiers sont exiges ; les deux suivants rassurent le voyageur sans fermer la
     * porte aux communes qui n'en delivrent pas.
     */
    public const DOCUMENTS = [
        PeerVehicleDocument::TYPE_ASSURANCE,
        PeerVehicleDocument::TYPE_TITRE,
        PeerVehicleDocument::TYPE_ENREGISTREMENT,
        PeerVehicleDocument::TYPE_ENERGIE,
    ];

    public const EQUIPEMENTS = [
        'wifi' => 'Wi-Fi', 'cuisine' => 'Cuisine', 'lave-linge' => 'Lave-linge',
        'seche-linge' => 'Sèche-linge', 'climatisation' => 'Climatisation', 'chauffage' => 'Chauffage',
        'television' => 'Télévision', 'parking' => 'Parking', 'ascenseur' => 'Ascenseur',
        'balcon' => 'Balcon', 'jardin' => 'Jardin', 'piscine' => 'Piscine',
        'lit-bebe' => 'Lit bébé', 'espace-travail' => 'Espace de travail',
        'detecteur-fumee' => 'Détecteur de fumée', 'animaux' => 'Animaux acceptés',
    ];

    public function mount(PeerStay $stay): void
    {
        // L'ANNONCE APPARTIENT A SON PROPRIETAIRE, et l'identifiant vient de l'URL : sans cette
        // garde, changer un chiffre dans la barre d'adresse ouvrirait l'annonce d'un autre.
        abort_unless($stay->owner_id === auth()->id(), 403);

        $this->stayId = (int) $stay->id;

        $this->titre = (string) $stay->title;
        $this->description = (string) $stay->description;
        $this->typeDeBien = (string) $stay->property_type;
        $this->typeDEspace = (string) $stay->space_type;
        $this->voyageursMax = (int) $stay->max_guests;
        $this->chambres = (int) $stay->bedrooms;
        $this->lits = (int) $stay->beds;
        $this->sallesDeBain = (string) $stay->bathrooms;
        $this->surface = $stay->surface_m2;
        $this->equipements = $stay->equipements();
        $this->reglement = (string) $stay->house_rules;

        $this->prixParNuit = (int) $stay->nightly_price_cents;
        $this->fraisDeMenage = (int) $stay->cleaning_fee_cents;
        $this->voyageursInclus = (int) $stay->guests_included;
        $this->prixVoyageurEnPlus = (int) $stay->extra_guest_price_cents;
        $this->remise3 = (int) $stay->discount_3_days_percent;
        $this->remise7 = (int) $stay->discount_7_days_percent;
        $this->remise28 = (int) $stay->discount_28_days_percent;
        $this->caution = (int) $stay->deposit_cents;

        $this->nuitsMin = (int) $stay->min_nights;
        $this->nuitsMax = (int) $stay->max_nights;
        $this->arriveeApres = substr((string) $stay->check_in_from, 0, 5);
        $this->departAvant = substr((string) $stay->check_out_before, 0, 5);
        $this->reservationInstantanee = (bool) $stay->instant_booking;
        $this->politiqueDAnnulation = (string) $stay->cancellation_policy;

        // LE PRIX DE SAISON S'APPLIQUAIT DEJA, SANS QUE PERSONNE PUISSE LE VOIR : la colonne
        // `pricing_rules` etait lue par le calcul et ecrite par aucun ecran, donc les valeurs
        // de `config/peer_rental.pricing` majoraient chaque annonce a l'insu de son hote.
        // On affiche ce qui s'applique vraiment, pour qu'enregistrer ne change rien en douce.
        $regles = $stay->reglesDePrix();
        $this->majorationWeekend = self::enPourcentage(
            $regles['weekend_multiplier'] ?? config('peer_rental.pricing.weekend_multiplier', 1.15)
        );
        $this->majorationHauteSaison = self::enPourcentage(
            $regles['high_season_multiplier'] ?? config('peer_rental.pricing.high_season_multiplier', 1.20)
        );
        /** @var list<int> $mois */
        $mois = $regles['high_season_months'] ?? config('peer_rental.pricing.high_season_months', []);
        $this->moisHauteSaison = array_map('intval', $mois);

        $this->adresse = (string) $stay->address_line;
        $this->codePostal = (string) $stay->postal_code;
        $this->ville = (string) $stay->city;
        $this->pays = (string) $stay->country_code;
    }

    /** Un multiplicateur se lit mal ; une majoration en pourcentage se saisit. */
    private static function enPourcentage(mixed $multiplicateur): int
    {
        return max(0, (int) round(((float) $multiplicateur - 1) * 100));
    }

    #[Computed]
    public function logement(): PeerStay
    {
        return PeerStay::query()->with(['media', 'indisponibilites', 'documents'])->findOrFail($this->stayId);
    }

    /**
     * CE QUI MANQUE POUR PUBLIER, DIT À L'AVANCE.
     *
     * Une annonce refusée après coup coûte au propriétaire une attente et une déception ; la
     * liste ci-dessous lui évite les deux.
     *
     * @return list<string>
     */
    #[Computed]
    public function motifsDeBlocage(): array
    {
        $logement = $this->logement();
        $motifs = [];

        if (trim((string) $logement->title) === '') {
            $motifs[] = __('Donnez un titre à votre annonce.');
        }

        if ($logement->media->isEmpty()) {
            $motifs[] = __('Ajoutez au moins une photo.');
        }

        if (trim((string) $logement->city) === '' || trim((string) $logement->address_line) === '') {
            $motifs[] = __('Indiquez où se trouve le logement.');
        }

        if ((int) $logement->nightly_price_cents <= 0) {
            $motifs[] = __('Fixez un prix par nuit.');
        }

        if (trim((string) $logement->description) === '') {
            $motifs[] = __('Décrivez le logement en quelques lignes.');
        }

        // LES PAPIERS EXIGES, DITS PAR LE BIEN. Un logement loue sans titre ni assurance
        // expose la plateforme autant que son proprietaire : la porte reste fermee.
        foreach ($logement->typesDeDocumentsRequis() as $type) {
            $valide = $logement->documents
                ->where('document_type', $type)
                ->contains(fn (PeerVehicleDocument $d): bool => $d->estValide());

            if (! $valide) {
                $motifs[] = __('Le document « :papier » doit être validé.', [
                    'papier' => __(PeerVehicleDocument::LIBELLES[$type] ?? $type),
                ]);
            }
        }

        if (! auth()->user()?->canReceiveStripeConnectPayments()) {
            $motifs[] = __('Terminez votre inscription au paiement pour être réglé.');
        }

        return $motifs;
    }

    public function enregistrer(): void
    {
        $this->erreur = null;

        $this->validate([
            'titre' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'typeDeBien' => ['required', 'in:'.implode(',', PeerStay::TYPES)],
            'typeDEspace' => ['required', 'in:'.implode(',', PeerStay::ESPACES)],
            'voyageursMax' => ['required', 'integer', 'min:1', 'max:50'],
            'chambres' => ['required', 'integer', 'min:0', 'max:50'],
            'lits' => ['required', 'integer', 'min:0', 'max:100'],
            'sallesDeBain' => ['required', 'numeric', 'min:0', 'max:50'],
            'surface' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'prixParNuit' => ['required', 'integer', 'min:100'],
            'fraisDeMenage' => ['required', 'integer', 'min:0'],
            'voyageursInclus' => ['required', 'integer', 'min:1'],
            'prixVoyageurEnPlus' => ['required', 'integer', 'min:0'],
            'remise3' => ['required', 'integer', 'min:0', 'max:90'],
            'remise7' => ['required', 'integer', 'min:0', 'max:90'],
            'remise28' => ['required', 'integer', 'min:0', 'max:90'],
            'caution' => ['required', 'integer', 'min:0'],
            'nuitsMin' => ['required', 'integer', 'min:1', 'max:365'],
            'nuitsMax' => ['required', 'integer', 'min:1', 'max:365'],
            'arriveeApres' => ['nullable', 'date_format:H:i'],
            'departAvant' => ['nullable', 'date_format:H:i'],
            // LES TROIS SEULES CLES QUE LE BAREME CONNAIT : hors de cette liste,
            // `fraisDAnnulation()` ne trouve aucun palier et retient TOUT le loyer.
            'politiqueDAnnulation' => ['required', 'in:souple,moderee,stricte'],
            'majorationWeekend' => ['required', 'integer', 'min:0', 'max:300'],
            'majorationHauteSaison' => ['required', 'integer', 'min:0', 'max:300'],
            'moisHauteSaison' => ['array'],
            'moisHauteSaison.*' => ['integer', 'min:1', 'max:12'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'codePostal' => ['nullable', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:120'],
            'pays' => ['required', 'string', 'size:2'],
        ]);

        // UN PLAFOND SOUS LE PLANCHER REND TOUTE RESERVATION IMPOSSIBLE, sans qu'aucun message ne
        // l'explique au voyageur. On le corrige a la saisie plutot que de le laisser vivre.
        if ($this->nuitsMax < $this->nuitsMin) {
            $this->nuitsMax = $this->nuitsMin;
        }

        // LES VOYAGEURS INCLUS NE DEPASSENT PAS LA CAPACITE : sinon le supplement ne s'applique
        // jamais, et le prix affiche ment sur les grands groupes.
        if ($this->voyageursInclus > $this->voyageursMax) {
            $this->voyageursInclus = $this->voyageursMax;
        }

        $this->logement()->update([
            'title' => $this->titre,
            'description' => $this->description ?: null,
            'property_type' => $this->typeDeBien,
            'space_type' => $this->typeDEspace,
            'max_guests' => $this->voyageursMax,
            'bedrooms' => $this->chambres,
            'beds' => $this->lits,
            'bathrooms' => $this->sallesDeBain,
            'surface_m2' => $this->surface,
            'amenities' => array_values(array_intersect($this->equipements, array_keys(self::EQUIPEMENTS))),
            'house_rules' => $this->reglement ?: null,
            'nightly_price_cents' => $this->prixParNuit,
            'cleaning_fee_cents' => $this->fraisDeMenage,
            'guests_included' => $this->voyageursInclus,
            'extra_guest_price_cents' => $this->prixVoyageurEnPlus,
            'discount_3_days_percent' => $this->remise3,
            'discount_7_days_percent' => $this->remise7,
            'discount_28_days_percent' => $this->remise28,
            'deposit_cents' => $this->caution,
            'min_nights' => $this->nuitsMin,
            'max_nights' => $this->nuitsMax,
            'check_in_from' => $this->arriveeApres ?: null,
            'check_out_before' => $this->departAvant ?: null,
            'instant_booking' => $this->reservationInstantanee,
            'cancellation_policy' => $this->politiqueDAnnulation,
            'pricing_rules' => [
                'weekend_multiplier' => 1 + $this->majorationWeekend / 100,
                'high_season_multiplier' => 1 + $this->majorationHauteSaison / 100,
                'high_season_months' => array_values(array_unique(array_map('intval', $this->moisHauteSaison))),
            ],
            'address_line' => $this->adresse ?: null,
            'postal_code' => $this->codePostal ?: null,
            'city' => $this->ville ?: null,
            'country_code' => strtoupper($this->pays),
        ]);

        unset($this->logement, $this->motifsDeBlocage);
        $this->message = __('Annonce enregistrée.');
    }

    public function ajouterDesPhotos(): void
    {
        $this->validate([
            'photos.*' => ['image', 'max:8192'],
        ]);

        $logement = $this->logement();
        $position = (int) $logement->media()->max('position');

        foreach ($this->photos as $photo) {
            $logement->media()->create([
                'path' => $photo->store('peer-stays', 'public'),
                'position' => ++$position,
            ]);
        }

        $this->photos = [];
        unset($this->logement, $this->motifsDeBlocage);
        $this->message = __('Photos ajoutées.');
    }

    /** LA COUVERTURE EST UNE POSITION : la photo choisie passe devant, les autres reculent. */
    public function definirLaCouverture(int $mediaId): void
    {
        $logement = $this->logement();

        $choisie = $logement->media->firstWhere('id', $mediaId);

        if (! $choisie instanceof PeerStayMedium) {
            return;
        }

        $position = 1;
        $choisie->forceFill(['position' => 0])->save();

        foreach ($logement->media->where('id', '!=', $mediaId) as $autre) {
            $autre->forceFill(['position' => $position++])->save();
        }

        unset($this->logement);
    }

    public function supprimerUnePhoto(int $mediaId): void
    {
        $this->logement()->media()->whereKey($mediaId)->delete();

        unset($this->logement, $this->motifsDeBlocage);
        $this->message = __('Photo retirée.');
    }

    /**
     * LE DEPOT D'UN PAPIER.
     *
     * Le fichier va sur le disque PRIVE : un titre de propriete porte un nom, une adresse et
     * parfois un numero national. Rien de tout cela n'a sa place derriere une URL publique.
     */
    public function deposerUnDocument(): void
    {
        $this->validate([
            'fichierDocument' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png'],
            'typeDocument' => ['required', 'in:'.implode(',', self::DOCUMENTS)],
            'expirationDocument' => ['nullable', 'date'],
        ]);

        $logement = $this->logement();
        $chemin = $this->fichierDocument->store('peer-documents/'.$logement->reference, 'local');

        $logement->documents()->create([
            'document_type' => $this->typeDocument,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => $chemin,
            'file_name' => $this->fichierDocument->getClientOriginalName(),
            'mime_type' => $this->fichierDocument->getMimeType(),
            'file_size' => $this->fichierDocument->getSize(),
            'expires_at' => $this->expirationDocument === '' ? null : $this->expirationDocument,
        ]);

        $this->fichierDocument = null;
        $this->expirationDocument = '';
        $this->message = __('Document déposé. Un administrateur le vérifie.');
        unset($this->logement, $this->motifsDeBlocage);
    }

    /**
     * RETIRER UN PAPIER REFUSE, pour en redeposer un lisible.
     *
     * Un papier DEJA VALIDE ne se retire pas : il justifie les sejours deja conclus, et le
     * faire disparaitre effacerait la preuve sur laquelle la plateforme s'est engagee.
     */
    public function supprimerUnDocument(int $documentId): void
    {
        $papier = $this->logement()->documents()->whereKey($documentId)->firstOrFail();

        if ($papier->status === PeerVehicleDocument::STATUT_VALIDE) {
            $this->erreur = __('Un document validé ne se retire pas.');

            return;
        }

        Storage::disk('local')->delete((string) $papier->file_path);
        $papier->delete();

        $this->message = __('Document retiré.');
        unset($this->logement, $this->motifsDeBlocage);
    }

    public function fermerUnePeriode(): void
    {
        $this->validate([
            'fermetureDebut' => ['required', 'date'],
            'fermetureFin' => ['required', 'date', 'after_or_equal:fermetureDebut'],
            'fermetureMotif' => ['nullable', 'string', 'max:200'],
        ]);

        $this->logement()->indisponibilites()->create([
            'starts_on' => $this->fermetureDebut,
            'ends_on' => $this->fermetureFin,
            'kind' => PeerVehicleAvailability::FERMEE,
            'reason' => $this->fermetureMotif ?: null,
        ]);

        $this->reset(['fermetureDebut', 'fermetureFin', 'fermetureMotif']);
        unset($this->logement);
        $this->message = __('Période fermée.');
    }

    public function rouvrirUnePeriode(int $periodeId): void
    {
        $this->logement()->indisponibilites()->whereKey($periodeId)->delete();

        unset($this->logement);
        $this->message = __('Période rouverte.');
    }

    /** PUBLIER — la demande part en vérification, elle ne publie pas d'elle-même. */
    public function demanderLaPublication(): void
    {
        $this->erreur = null;

        if ($this->motifsDeBlocage() !== []) {
            $this->erreur = __('Complétez d’abord ce qui manque.');

            return;
        }

        $this->logement()->forceFill(['status' => PeerStay::STATUT_EN_REVUE])->save();

        unset($this->logement, $this->motifsDeBlocage);
        $this->message = __('Annonce envoyée en vérification.');
    }

    /** METTRE EN PAUSE — les séjours déjà réservés continuent, l'annonce disparaît du catalogue. */
    public function mettreEnPause(): void
    {
        $this->logement()->forceFill(['status' => PeerStay::STATUT_SUSPENDU])->save();

        unset($this->logement, $this->motifsDeBlocage);
        $this->message = __('Annonce mise en pause. Les séjours en cours continuent.');
    }

    public function reprendre(): void
    {
        if ($this->motifsDeBlocage() !== []) {
            $this->erreur = __('Complétez d’abord ce qui manque.');

            return;
        }

        $logement = $this->logement();

        $logement->forceFill([
            'status' => PeerStay::STATUT_PUBLIE,
            'published_at' => $logement->published_at ?? Carbon::now(),
        ])->save();

        unset($this->logement, $this->motifsDeBlocage);
        $this->message = __('Annonce de nouveau visible.');
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-stay-editor');
    }
}
