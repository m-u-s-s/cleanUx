<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UN FAIT, ET RIEN QU'UN FAIT : à telle date, sur telle mission, une révision a été proposée et
 * voici ce que le client en a fait.
 *
 * ── POURQUOI LES DEUX CONTREPARTIES SONT SUR LA MÊME LIGNE ───────────────────────────────────
 *
 * Le signal décisif n'est ni « celui-ci révise souvent » ni « celui-là refuse souvent » — ces deux
 * mesures se fabriquent seul. C'est la CONCORDANCE DES TIERS qui tranche : un client révisé par
 * plusieurs prestataires qui ne se connaissent pas, ou un prestataire qui révise chez plusieurs
 * clients que personne d'autre n'a jamais révisés.
 *
 * C'est aussi la garantie qu'un menteur ne fera jamais sanctionner un honnête : il lui faudrait
 * recruter plusieurs contreparties indépendantes mentant toutes dans le même sens.
 *
 * ── UN FAIT NE SE CORRIGE PAS ────────────────────────────────────────────────────────────────
 *
 * Les décisions — suspendre, lever — vivent dans {@see MissionFeatureSuspension}. Les fondre ici
 * donnerait une table qu'on modifie pour changer un état, effaçant au passage la trace qui l'a
 * justifiée.
 */
class MissionDisputeSignal extends Model
{
    /** Le client a reconnu que le devis était trop bas. */
    public const ISSUE_ACCEPTEE = 'accepted';

    /** Il a contesté, mais garde la prestation au prix d'origine. */
    public const ISSUE_REFUSEE_POURSUITE = 'declined_continue';

    /** Il a contesté et tout arrêté — c'est aussi le marqueur d'entente le plus fort. */
    public const ISSUE_REFUSEE_ARRET = 'declined_stop';

    public const ISSUE_EXPIREE = 'expired';

    public const COTE_CLIENT = 'client';

    public const COTE_PRESTATAIRE = 'provider';

    public const COTE_INDETERMINE = 'undetermined';

    public const VERDICT_AUCUN = 'none';

    public const VERDICT_CLIENT = 'client_at_fault';

    public const VERDICT_PRESTATAIRE = 'provider_at_fault';

    public const VERDICT_INDECIS = 'inconclusive';

    protected $fillable = [
        'mission_id',
        'booking_id',
        'quote_revision_id',
        'cancellation_id',
        'provider_user_id',
        'client_user_id',
        'signal_code',
        'charged_side',
        'outcome',
        'evidence',
        'verdict',
        'verdict_at',
        'reviewed_by_user_id',
    ];

    protected $casts = [
        'evidence' => 'array',
        'verdict_at' => 'datetime',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}
