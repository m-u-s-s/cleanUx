<?php

namespace App\Models;

use Database\Factories\PeerVehicleAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * UNE PERIODE OUVERTE OU FERMEE.
 *
 * Le defaut d'un vehicule publie est DISPONIBLE : ces lignes ne disent que les exceptions.
 * L'inverse aurait oblige le proprietaire a declarer chaque jour de l'annee.
 */
class PeerVehicleAvailability extends Model
{
    /** @use HasFactory<PeerVehicleAvailabilityFactory> */
    use HasFactory;

    protected $table = 'peer_vehicle_availability';

    /**
     * LES DEUX COLONNES DISENT LA MEME CHOSE, ET DOIVENT LE DIRE ENSEMBLE.
     *
     * Tout le module vehicules ecrit `peer_vehicle_id` ; la couche partagee lit `rentable_*`. Sans
     * ce crochet, une indisponibilite posee par l'ancienne voie serait INVISIBLE a la nouvelle, et
     * un vehicule deja loue reapparaitrait libre — le pire defaut possible sur un calendrier.
     */
    protected static function booted(): void
    {
        static::saving(function (self $ligne) {
            if ($ligne->rentable_type === null && $ligne->peer_vehicle_id !== null) {
                $ligne->rentable_type = PeerVehicle::class;
                $ligne->rentable_id = $ligne->peer_vehicle_id;
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function rentable(): MorphTo
    {
        return $this->morphTo();
    }

    public const FERMEE = 'blocked';

    /** Rouvre une periode a l'interieur d'une fermeture plus large. */
    public const OUVERTE = 'open';

    protected $fillable = [
        'peer_vehicle_id', 'rentable_type', 'rentable_id', 'starts_on', 'ends_on', 'kind', 'reason',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return BelongsTo<PeerVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(PeerVehicle::class, 'peer_vehicle_id');
    }
}
