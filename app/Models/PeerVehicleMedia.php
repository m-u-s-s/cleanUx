<?php

namespace App\Models;

use Database\Factories\PeerVehicleMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** UNE PHOTO D'ANNONCE. La couverture est celle que voit la recherche. */
class PeerVehicleMedia extends Model
{
    /** @use HasFactory<PeerVehicleMediaFactory> */
    use HasFactory;

    protected $table = 'peer_vehicle_media';

    protected $fillable = [
        'peer_vehicle_id', 'path', 'caption', 'sort_order', 'is_cover', 'sha256',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<PeerVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(PeerVehicle::class, 'peer_vehicle_id');
    }
}
