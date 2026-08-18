<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UNE RÉPONSE POSSIBLE — son code stable, ce qu'on vérifie avant de la proposer, ce qu'elle
 * déclenche.
 *
 * `code` ALIMENTE `booking_cancellations_v2.reason_code`, et c'est sur lui que
 * `CancellationEngine::quote()` retrouve un motif exempté. Il ne se réutilise donc jamais : un code
 * recyclé ferait relire un dossier ancien avec le sens d'aujourd'hui.
 */
class CancellationQuestionOption extends Model
{
    /** Rien à vérifier : la réponse engage celui qui la donne, elle ne se prouve pas. */
    public const VERIF_AUCUNE = 'none';

    /** Le serveur connaît `planned_start_at` et le statut réel. */
    public const VERIF_RETARD = 'provider_late';

    /** La trace GPS montre-t-elle un déplacement vers le lieu ? */
    public const VERIF_DEPLACEMENT = 'gps_movement';

    /** Ping, SMS, appel : a-t-on réellement tenté de joindre le client ? */
    public const VERIF_CLIENT_INJOIGNABLE = 'client_unreachable';

    public const ISSUE_ANNULER = 'cancel';

    /** Le travail ne correspond pas : ce n'est pas une annulation, c'est un nouveau devis. */
    public const ISSUE_VERS_DEVIS = 'redirect_requote';

    /** Le chantier est trop gros : un renfort, pas un abandon. */
    public const ISSUE_VERS_RENFORT = 'redirect_reinforcement';

    /** Le client ne répond pas : le no-show existe et s'ouvre après un délai serveur. */
    public const ISSUE_VERS_ABSENCE = 'redirect_noshow';

    public const ISSUE_REVUE = 'review';

    use SoftDeletes;

    protected $fillable = [
        'cancellation_question_id', 'code', 'label', 'sort_order', 'is_active',
        'verification', 'outcome', 'exempt_reason_id', 'collusion_signal',
        'requires_text', 'requires_proof', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'collusion_signal' => 'boolean',
        'requires_text' => 'boolean',
        'requires_proof' => 'boolean',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<CancellationQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CancellationQuestion::class, 'cancellation_question_id');
    }

    /** @return BelongsTo<CancellationExemptReason, $this> */
    public function exemptReason(): BelongsTo
    {
        return $this->belongsTo(CancellationExemptReason::class, 'exempt_reason_id');
    }

    /** Cette option mène-t-elle ailleurs qu'à une annulation ? */
    public function estUnAiguillage(): bool
    {
        return in_array($this->outcome, [
            self::ISSUE_VERS_DEVIS,
            self::ISSUE_VERS_RENFORT,
            self::ISSUE_VERS_ABSENCE,
        ], true);
    }
}
