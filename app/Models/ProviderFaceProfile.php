<?php

namespace App\Models;

use App\Casts\EncryptedArrayFallback;
use Database\Factories\ProviderFaceProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Le visage de référence d'un prestataire, et l'état courant de sa conformité faciale.
 *
 * Une ligne par prestataire. C'est la seule source de vérité sur trois questions :
 *   — ce prestataire a-t-il enrôlé son visage ?
 *   — un contrôle lui est-il dû maintenant ?
 *   — est-il bloqué ?
 *
 * @property string $status
 * @property ?string $reference_path
 * @property ?Carbon $captured_at
 * @property ?Carbon $consent_given_at
 * @property ?string $consent_version
 * @property ?Carbon $consent_withdrawn_at
 * @property ?string $id_match_status
 * @property ?string $id_match_score
 * @property ?Carbon $id_match_checked_at
 * @property ?Carbon $last_check_at
 * @property int $consecutive_failures
 * @property ?string $block_reason
 * @property ?Carbon $next_check_due_at
 * @property ?Carbon $blocked_at
 * @property ?array<string, mixed> $metadata
 */
class ProviderFaceProfile extends Model
{
    /** @use HasFactory<ProviderFaceProfileFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    public const MATCH_PENDING = 'pending';

    public const MATCH_OK = 'match';

    public const MATCH_MISMATCH = 'mismatch';

    public const MATCH_INCONCLUSIVE = 'inconclusive';

    public const MATCH_MANUAL_OVERRIDE = 'manual_override';

    public const BLOCK_FAILED_CHECKS = 'failed_checks';

    public const BLOCK_ID_MISMATCH = 'id_mismatch';

    public const BLOCK_CONSENT_WITHDRAWN = 'consent_withdrawn';

    public const BLOCK_ADMIN = 'admin_decision';

    /**
     * `next_check_due_at`, `blocked_at`, `block_reason`, `status` et `consecutive_failures` sont
     * VOLONTAIREMENT hors `$fillable` : ce sont les colonnes qui portent la garde. Les rendre
     * assignables en masse rendrait la garde optionnelle — c'est exactement la porte qu'on ferme
     * ici. Elles s'écrivent par `forceFill()`, depuis les services du module et nulle part ailleurs.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'reference_path',
        'reference_hash',
        'reference_mime',
        'external_face_id',
        'captured_at',
        'captured_ip_hash',
        'captured_device_name',
        'consent_given_at',
        'consent_version',
        'consent_withdrawn_at',
        'id_document_id',
        'id_match_status',
        'id_match_score',
        'id_match_checked_at',
        'id_match_provider',
        'last_check_at',
        'unblocked_at',
        'unblocked_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'metadata',
    ];

    /**
     * Le défaut SQL ne remplit pas l'objet en mémoire : `status` et `id_match_status` ne sont pas
     * assignables en masse, `create()` les rendrait donc à `null` en PHP alors que la ligne porte
     * bien sa valeur. Voir le même commentaire sur `ProviderFaceCheck`.
     */
    protected static function booted(): void
    {
        static::creating(function (self $profil): void {
            $profil->status ??= self::STATUS_PENDING;
            $profil->id_match_status ??= self::MATCH_PENDING;
            $profil->consecutive_failures ??= 0;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'consent_withdrawn_at' => 'datetime',
            'id_match_checked_at' => 'datetime',
            'id_match_score' => 'decimal:2',
            'next_check_due_at' => 'datetime',
            'last_check_at' => 'datetime',
            'consecutive_failures' => 'integer',
            'blocked_at' => 'datetime',
            'unblocked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => EncryptedArrayFallback::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProviderOnboardingDocument, $this> */
    public function idDocument(): BelongsTo
    {
        return $this->belongsTo(ProviderOnboardingDocument::class, 'id_document_id');
    }

    /** @return HasMany<ProviderFaceCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ProviderFaceCheck::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isEnrolled(): bool
    {
        return $this->status === self::STATUS_ENROLLED && $this->reference_path !== null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Le consentement est retirable, et son retrait rend le visage inutilisable.
     */
    public function hasActiveConsent(): bool
    {
        return $this->consent_given_at !== null && $this->consent_withdrawn_at === null;
    }

    /**
     * Une échéance NULLE vaut « dû » : c'est le cas d'un profil qui vient d'être enrôlé et dont
     * aucun contrôle n'a encore fixé la suivante. Le défaut penche du côté du contrôle.
     */
    public function isCheckDue(): bool
    {
        return $this->next_check_due_at === null || $this->next_check_due_at->isPast();
    }

    /**
     * @param  Builder<ProviderFaceProfile>  $query
     * @return Builder<ProviderFaceProfile>
     */
    public function scopeEnrolled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENROLLED);
    }

    /**
     * @param  Builder<ProviderFaceProfile>  $query
     * @return Builder<ProviderFaceProfile>
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereNotNull('blocked_at');
    }

    /**
     * Ce qui attend un humain : jamais enrôlé, appariement douteux, ou bloqué.
     *
     * @param  Builder<ProviderFaceProfile>  $query
     * @return Builder<ProviderFaceProfile>
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->where('status', self::STATUS_PENDING)
                ->orWhereIn('id_match_status', [self::MATCH_MISMATCH, self::MATCH_INCONCLUSIVE])
                ->orWhereNotNull('blocked_at');
        });
    }
}
