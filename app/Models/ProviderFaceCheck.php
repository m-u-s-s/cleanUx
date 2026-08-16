<?php

namespace App\Models;

use App\Casts\EncryptedArrayFallback;
use Database\Factories\ProviderFaceCheckFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un contrôle facial : demandé, puis répondu (ou abandonné, ou expiré).
 *
 * `abandoned` n'est PAS `failed`. Un prestataire qui quitte l'écran n'a rien prouvé, mais il n'a
 * rien raté non plus : un réseau qui lâche, une batterie vide, un appel entrant produisent le même
 * état. C'est la RÉPÉTITION de l'abandon qui devient un signal — jamais la première fois.
 *
 * @property string $status
 * @property string $triggered_by
 * @property ?string $decision_source
 * @property ?string $liveness_result
 * @property ?string $failure_reason
 * @property ?string $selfie_path
 * @property int $attempt_number
 * @property ?Carbon $requested_at
 * @property ?Carbon $answered_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $selfie_purged_at
 * @property ?array<string, mixed> $raw
 */
class ProviderFaceCheck extends Model
{
    /** @use HasFactory<ProviderFaceCheckFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ERROR = 'error';

    public const TRIGGER_ENROLLMENT = 'enrollment';

    public const TRIGGER_INTERVAL = 'interval';

    public const TRIGGER_RISK_DEVICE = 'risk_device';

    public const TRIGGER_RISK_FAILURES = 'risk_failures';

    public const TRIGGER_RISK_ABANDONS = 'risk_abandons';

    public const TRIGGER_ADMIN_FORCED = 'admin_forced';

    public const LIVENESS_PASS = 'pass';

    public const LIVENESS_FAIL = 'fail';

    public const LIVENESS_UNKNOWN = 'unknown';

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_MANUAL = 'manual';

    /**
     * `status`, `score`, `liveness_result` et `decision_source` sont hors `$fillable` : ce sont les
     * colonnes de verdict. Seul `FaceCheckService` les écrit, par `forceFill()`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider_face_profile_id',
        'triggered_by',
        'match_provider',
        'external_check_id',
        'selfie_path',
        'selfie_purged_at',
        'attempt_number',
        'requested_at',
        'answered_at',
        'expires_at',
        'ip_hash',
        'device_name',
        'app_version',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    /**
     * LE DÉFAUT SQL NE REMPLIT PAS L'OBJET EN MÉMOIRE.
     *
     * `status` n'est pas assignable en masse — c'est voulu, c'est la colonne de verdict. Mais du
     * coup `create()` rend un modèle dont `status` vaut `null` en PHP alors que la ligne vaut bien
     * `pending` en base : la valeur par défaut est posée par le moteur, pas par Eloquent.
     *
     * Conséquence mesurée : le contrôle qu'on venait d'ouvrir était refusé à la soumission avec
     * « ce contrôle est déjà clos », parce que `null !== 'pending'`. Le défaut appartient donc au
     * code, la colonne n'en garde qu'une copie de sécurité.
     */
    protected static function booted(): void
    {
        static::creating(function (self $controle): void {
            $controle->status ??= self::STATUS_PENDING;
            $controle->attempt_number ??= 1;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'attempt_number' => 'integer',
            'requested_at' => 'datetime',
            'answered_at' => 'datetime',
            'expires_at' => 'datetime',
            'selfie_purged_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'raw' => EncryptedArrayFallback::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProviderFaceProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProviderFaceProfile::class, 'provider_face_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<ProviderFaceCheck>  $query
     * @return Builder<ProviderFaceCheck>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<ProviderFaceCheck>  $query
     * @return Builder<ProviderFaceCheck>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
