<?php

namespace App\Models;

use Database\Factories\PeerVehicleAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public const FERMEE = 'blocked';

    /** Rouvre une periode a l'interieur d'une fermeture plus large. */
    public const OUVERTE = 'open';

    protected $fillable = [
        'peer_vehicle_id', 'starts_on', 'ends_on', 'kind', 'reason',
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
