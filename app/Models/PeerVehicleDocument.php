<?php

namespace App\Models;

use Database\Factories\PeerVehicleDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * LES PAPIERS D'UN BIEN — carte grise et assurance pour une voiture, assurance et titre de
 * propriete pour un logement.
 *
 * La table garde son nom : la renommer imposerait de toucher tout le module vehicules vivant
 * pour un gain cosmetique. Ce que le bien exige, c'est le bien qui le dit.
 */
class PeerVehicleDocument extends Model
{
    /** @use HasFactory<PeerVehicleDocumentFactory> */
    use HasFactory;

    public const TYPE_CARTE_GRISE = 'registration';

    public const TYPE_ASSURANCE = 'insurance';

    public const TYPE_CONTROLE_TECHNIQUE = 'technical_inspection';

    /** Les deux exiges d'un vehicule avant publication : sans eux, l'annonce reste en revue. */
    public const TYPES_REQUIS = [self::TYPE_CARTE_GRISE, self::TYPE_ASSURANCE];

    /** LE TITRE QUI PROUVE LE DROIT DE LOUER : acte de propriete ou mandat de gestion. */
    public const TYPE_TITRE = 'ownership';

    /**
     * LE NUMERO D'ENREGISTREMENT COMMUNAL.
     *
     * Bruxelles, Paris et bien d'autres l'exigent pour la location de courte duree. Il n'est
     * pas requis partout : le rendre obligatoire fermerait la porte aux communes qui n'en
     * delivrent pas.
     */
    public const TYPE_ENREGISTREMENT = 'registration_number';

    /** Certificat de performance energetique (PEB, DPE). */
    public const TYPE_ENERGIE = 'energy_certificate';

    /** LES LIBELLES, pour que l'ecran ne les reinvente pas chacun de son cote. */
    public const LIBELLES = [
        self::TYPE_CARTE_GRISE => 'Carte grise',
        self::TYPE_ASSURANCE => 'Attestation d’assurance',
        self::TYPE_CONTROLE_TECHNIQUE => 'Contrôle technique',
        self::TYPE_TITRE => 'Titre de propriété ou mandat de gestion',
        self::TYPE_ENREGISTREMENT => 'Numéro d’enregistrement communal',
        self::TYPE_ENERGIE => 'Certificat énergétique (PEB/DPE)',
    ];

    public const STATUT_EN_REVUE = 'pending_review';

    public const STATUT_VALIDE = 'approved';

    public const STATUT_REFUSE = 'rejected';

    protected $fillable = [
        'peer_vehicle_id', 'documentable_type', 'documentable_id',
        'document_type', 'status', 'file_path', 'file_name',
        'mime_type', 'file_size', 'expires_at', 'rejection_reason',
        'reviewed_by', 'reviewed_at', 'metadata',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * LES DEUX COLONNES DISENT LE MEME BIEN, ET DOIVENT LE DIRE ENSEMBLE.
     *
     * Meme crochet que sur `peer_rentals` et le calendrier : sans lui, un papier depose par
     * l'ancienne voie serait invisible a la file d'attente partagee.
     */
    protected static function booted(): void
    {
        static::saving(function (self $papier) {
            if ($papier->documentable_type === null && $papier->peer_vehicle_id !== null) {
                $papier->documentable_type = PeerVehicle::class;
                $papier->documentable_id = $papier->peer_vehicle_id;
            }

            // ET DANS L'AUTRE SENS : depuis que la relation est polymorphe, l'editeur de
            // vehicule n'ecrit plus l'ancienne colonne. La laisser vide ferait disparaitre
            // le papier au premier retour en arriere de la migration.
            if ($papier->peer_vehicle_id === null && $papier->documentable_type === PeerVehicle::class) {
                $papier->peer_vehicle_id = $papier->documentable_id;
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<PeerVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(PeerVehicle::class, 'peer_vehicle_id');
    }

    public function libelle(): string
    {
        return self::LIBELLES[$this->document_type] ?? (string) $this->document_type;
    }

    /** Un papier perime ne vaut pas mieux qu'un papier absent. */
    public function estValide(): bool
    {
        return $this->status === self::STATUT_VALIDE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
