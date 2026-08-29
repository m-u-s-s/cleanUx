<?php

namespace App\Models;

use Database\Factories\PeerInspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L'ETAT DES LIEUX, AU DEPART ET AU RETOUR.
 *
 * C'est lui qui fait foi en cas de dommage : la difference entre les deux etats est la seule
 * mesure opposable, et les photos portent leur empreinte et leur horodatage.
 */
class PeerInspection extends Model
{
    /** @use HasFactory<PeerInspectionFactory> */
    use HasFactory;

    public const PHASE_DEPART = 'departure';

    public const PHASE_RETOUR = 'return';

    /** Les six angles exiges : sans eux, un dommage constate au retour n'est pas opposable. */
    public const ANGLES_REQUIS = ['front', 'rear', 'left', 'right', 'dashboard', 'interior'];

    protected $fillable = [
        'peer_rental_id', 'phase', 'mileage_km', 'fuel_eighths', 'cleanliness',
        'license_verified', 'notes', 'created_by', 'owner_signed_at', 'renter_signed_at',
    ];

    protected $casts = [
        'owner_signed_at' => 'datetime',
        'renter_signed_at' => 'datetime',
        'license_verified' => 'boolean',
        'mileage_km' => 'integer',
        'fuel_eighths' => 'integer',
    ];

    /** @return BelongsTo<PeerRental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(PeerRental::class, 'peer_rental_id');
    }

    /** @return HasMany<PeerInspectionPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(PeerInspectionPhoto::class, 'peer_inspection_id');
    }

    public function signeParLesDeux(): bool
    {
        return $this->owner_signed_at !== null && $this->renter_signed_at !== null;
    }

    /** @return list<string> les angles qui manquent encore */
    public function anglesManquants(): array
    {
        $presents = $this->photos->pluck('angle')->all();

        return array_values(array_diff(self::ANGLES_REQUIS, $presents));
    }
}
