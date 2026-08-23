<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** UNE RÉVISION DE DEVIS — le prix était faux dès le départ, et voici ce qu'il devrait être. */
class MissionQuoteRevision extends Model
{
    public const STATUT_PROPOSEE = 'proposed';

    public const STATUT_ACCEPTEE = 'accepted';

    public const STATUT_REFUSEE = 'declined';

    public const STATUT_EXPIREE = 'expired';

    /** L'accord est donné, l'argent n'a pas suivi. Deux états distincts, comme pour les suppléments. */
    public const STATUT_PAIEMENT_ECHOUE = 'payment_failed';

    public const STATUT_RETIREE = 'withdrawn';

    public const DECISION_POURSUIVRE = 'continue';

    public const DECISION_ARRETER = 'stop';

    protected $fillable = [
        'mission_id',
        'booking_id',
        'proposed_by_user_id',
        'original_total_cents',
        'proposed_service_cents',
        'revised_total_cents',
        'discount_breakdown',
        'currency',
        'reason_code',
        'reason_text',
        'evidence_media_ids',
        'status',
        'window_closes_at',
        'responded_at',
        'client_decision',
        'top_up_payment_intent_id',
        'charged_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'original_total_cents' => 'integer',
        'proposed_service_cents' => 'integer',
        'revised_total_cents' => 'integer',
        'discount_breakdown' => 'array',
        'evidence_media_ids' => 'array',
        'window_closes_at' => 'datetime',
        'responded_at' => 'datetime',
        'charged_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /** La révision attend-elle encore une réponse du client ? */
    public function attendLeClient(): bool
    {
        return $this->status === self::STATUT_PROPOSEE;
    }

    /** L'intervention doit-elle s'arrêter ? Portée par la révision, exécutée par l'annulation. */
    public function doitEtreAnnulee(): bool
    {
        return $this->status === self::STATUT_REFUSEE
            && $this->client_decision === self::DECISION_ARRETER;
    }

    /** Ce que le complément doit encaisser — jamais négatif : une baisse se règle par capture partielle. */
    public function complementCents(): int
    {
        return max(0, $this->revised_total_cents - $this->original_total_cents);
    }
}
