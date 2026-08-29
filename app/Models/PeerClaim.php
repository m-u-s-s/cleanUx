<?php

namespace App\Models;

use Database\Factories\PeerClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UNE RETENUE DEMANDEE SUR LA CAUTION.
 *
 * Le proprietaire la depose au retour, le locataire l'accepte ou la conteste. Tant qu'elle
 * est ouverte, la caution reste bloquee : la liberer d'abord reviendrait a arbitrer en silence.
 */
class PeerClaim extends Model
{
    /** @use HasFactory<PeerClaimFactory> */
    use HasFactory;

    public const MOTIF_DOMMAGE = 'damage';

    public const MOTIF_RETARD = 'late_return';

    public const MOTIF_CARBURANT = 'fuel';

    public const MOTIF_KILOMETRAGE = 'mileage';

    public const MOTIF_NETTOYAGE = 'cleaning';

    public const STATUT_OUVERTE = 'open';

    /** Le locataire reconnait : la retenue s'applique sans arbitrage. */
    public const STATUT_ACCEPTEE = 'accepted';

    public const STATUT_CONTESTEE = 'disputed';

    public const STATUT_RESOLUE = 'resolved';

    public const STATUT_ABANDONNEE = 'withdrawn';

    protected $fillable = [
        'peer_rental_id', 'opened_by', 'kind', 'amount_cents', 'status', 'description',
        'evidence', 'deposit_captured_cents', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
        'amount_cents' => 'integer',
        'deposit_captured_cents' => 'integer',
    ];

    /** @return BelongsTo<PeerRental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(PeerRental::class, 'peer_rental_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function estEnCours(): bool
    {
        return in_array($this->status, [self::STATUT_OUVERTE, self::STATUT_CONTESTEE], true);
    }
}
