<?php

namespace App\Models;

use Database\Factories\PeerInspectionPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** UNE PHOTO D'ETAT DES LIEUX, horodatee et empreintee comme une preuve. */
class PeerInspectionPhoto extends Model
{
    /** @use HasFactory<PeerInspectionPhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'peer_inspection_id', 'path', 'angle', 'caption', 'sha256',
        'taken_at', 'lat', 'lng', 'uploaded_by',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
    ];

    /** @return BelongsTo<PeerInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(PeerInspection::class, 'peer_inspection_id');
    }
}
