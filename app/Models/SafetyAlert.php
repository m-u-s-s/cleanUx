<?php

namespace App\Models;

use Database\Factories\SafetyAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * UNE ALERTE DE SÉCURITÉ DÉCLENCHÉE SUR LE TERRAIN (E33). À NE PAS CONFONDRE AVEC UN SIGNALEMENT.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $mission_id
 * @property string $level
 * @property string $status
 * @property float|null $lat
 * @property float|null $lng
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 */
class SafetyAlert extends Model
{
    /** @use HasFactory<SafetyAlertFactory> */
    use HasFactory;

    /** « Je ne me sens pas à l'aise, gardez un œil. » */
    public const LEVEL_CHECK_IN = 'check_in';

    /** « Venez. » */
    public const LEVEL_EMERGENCY = 'emergency';

    public const STATUS_OPEN = 'open';

    /** Quelqu'un l'a VUE — c'est ce que la personne sur place attend de savoir en premier. */
    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    /** Conservée : une fausse alerte effacée empêche de voir qu'un bouton se déclenche tout seul. */
    public const STATUS_FALSE_ALARM = 'false_alarm';

    protected $fillable = [
        'user_id',
        'mission_id',
        'booking_id',
        'level',
        'status',
        'message',
        'lat',
        'lng',
        'accuracy_m',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'resolved_at',
        'resolution_note',
        'emergency_contact_name',
        'emergency_contact_phone',
        'contact_notified_at',
        'metadata',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'accuracy_m' => 'integer',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'contact_notified_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return HasMany<SafetyAlertPing, $this> */
    public function pings(): HasMany
    {
        return $this->hasMany(SafetyAlertPing::class);
    }

    /** Une alerte qui attend encore quelqu'un. */
    public function estOuverte(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED], true);
    }
}
