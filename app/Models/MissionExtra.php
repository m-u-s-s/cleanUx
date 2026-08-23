<?php

namespace App\Models;

use Database\Factories\MissionExtraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UN SUPPLÉMENT PROPOSÉ SUR PLACE, et la réponse du client.
 *
 * @property int $id
 * @property int $mission_id
 * @property int|null $proposed_by_user_id
 * @property string $label
 * @property string|null $description
 * @property int $price_cents
 * @property string $currency
 * @property int|null $price_quote_id
 * @property string $status
 * @property Carbon|null $approved_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $charged_at
 * @property string|null $stripe_payment_intent_id
 * @property array<string, mixed>|null $metadata
 */
class MissionExtra extends Model
{
    /** @use HasFactory<MissionExtraFactory> */
    use HasFactory;

    /** En attente de la réponse du client. */
    public const STATUS_PROPOSED = 'proposed';

    /** Le client a dit oui — l'argent n'a pas encore bougé pour autant. */
    public const STATUS_APPROVED = 'approved';

    /** Le client a dit non. Le refus est une réponse, pas une panne : il se conserve. */
    public const STATUS_DECLINED = 'declined';

    /** L'argent a réellement été prélevé. SÉPARÉ D'`approved` À DESSEIN. */
    public const STATUS_CHARGED = 'charged';

    protected $fillable = [
        'mission_id',
        'proposed_by_user_id',
        'label',
        'description',
        'price_cents',
        'currency',
        'price_quote_id',
        'status',
        'approved_at',
        'declined_at',
        'charged_at',
        'metadata',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'price_quote_id' => 'integer',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'charged_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /** Le client n'a pas encore répondu. */
    public function estEnAttente(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    /** Le client a dit oui, que l'argent ait bougé ou non. */
    public function estAccepte(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_CHARGED], true);
    }

    public function montantEnEuros(): float
    {
        return round($this->price_cents / 100, 2);
    }
}
