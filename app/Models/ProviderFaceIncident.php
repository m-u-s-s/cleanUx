<?php

namespace App\Models;

use Database\Factories\ProviderFaceIncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ce qui appelle un humain : une panne signalée par le prestataire, ou un motif de soupçon.
 *
 * Les deux vivent dans la même table parce que l'admin les traite au même endroit et avec les
 * mêmes gestes (accuser réception, résoudre, écarter). Ce qui les distingue est `type`, et
 * `severity` dit à quel point ça presse.
 *
 * @property string $type
 * @property string $severity
 * @property string $status
 * @property int $occurrence_count
 * @property ?Carbon $acknowledged_at
 * @property ?Carbon $resolved_at
 * @property ?array<string, mixed> $diagnostics
 */
class ProviderFaceIncident extends Model
{
    /** @use HasFactory<ProviderFaceIncidentFactory> */
    use HasFactory;

    public const TYPE_PROVIDER_REPORT = 'provider_report';

    public const TYPE_REPEATED_ABANDON = 'repeated_abandon';

    public const TYPE_REPEATED_FAILURE = 'repeated_failure';

    public const TYPE_LIVENESS_FAIL = 'liveness_fail';

    public const TYPE_ID_MISMATCH = 'id_mismatch';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'provider_face_check_id',
        'type',
        'severity',
        'message',
        'diagnostics',
        'occurrence_count',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'resolved_by_user_id',
        'resolved_at',
        'resolution',
        'resolution_note',
    ];

    /**
     * Le défaut SQL ne remplit pas l'objet en mémoire : `status` n'est pas assignable en masse,
     * `create()` le rendrait donc à `null` et `isOpen()` répondrait faux sur un incident qu'on
     * vient d'ouvrir. Voir le même commentaire sur `ProviderFaceCheck`.
     */
    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->status ??= self::STATUS_OPEN;
            $incident->severity ??= self::SEVERITY_INFO;
            $incident->occurrence_count ??= 1;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'diagnostics' => 'array',
            'occurrence_count' => 'integer',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProviderFaceCheck, $this> */
    public function check(): BelongsTo
    {
        return $this->belongsTo(ProviderFaceCheck::class, 'provider_face_check_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED], true);
    }

    /**
     * @param  Builder<ProviderFaceIncident>  $query
     * @return Builder<ProviderFaceIncident>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * @param  Builder<ProviderFaceIncident>  $query
     * @return Builder<ProviderFaceIncident>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
