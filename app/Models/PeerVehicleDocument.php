<?php

namespace App\Models;

use Database\Factories\PeerVehicleDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** LES PAPIERS DU VEHICULE — carte grise, assurance, controle technique. */
class PeerVehicleDocument extends Model
{
    /** @use HasFactory<PeerVehicleDocumentFactory> */
    use HasFactory;

    public const TYPE_CARTE_GRISE = 'registration';

    public const TYPE_ASSURANCE = 'insurance';

    public const TYPE_CONTROLE_TECHNIQUE = 'technical_inspection';

    /** Les trois exiges avant publication : sans eux, l'annonce reste en revue. */
    public const TYPES_REQUIS = [self::TYPE_CARTE_GRISE, self::TYPE_ASSURANCE];

    public const STATUT_EN_REVUE = 'pending_review';

    public const STATUT_VALIDE = 'approved';

    public const STATUT_REFUSE = 'rejected';

    protected $fillable = [
        'peer_vehicle_id', 'document_type', 'status', 'file_path', 'file_name',
        'mime_type', 'file_size', 'expires_at', 'rejection_reason',
        'reviewed_by', 'reviewed_at', 'metadata',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<PeerVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(PeerVehicle::class, 'peer_vehicle_id');
    }

    /** Un papier perime ne vaut pas mieux qu'un papier absent. */
    public function estValide(): bool
    {
        return $this->status === self::STATUT_VALIDE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
