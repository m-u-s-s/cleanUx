<?php

namespace App\Models;

use App\Services\Audit\Concerns\AuditsEloquentEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un compte bancaire déclaré comme destination de la commission Brio, pour une devise donnée.
 *
 * Registre d'attestation : la destination réelle des versements est réglée chez Stripe. Voir la
 * migration `creer_le_registre_de_reglement` pour le raisonnement.
 *
 * @property string $currency
 * @property string $role
 * @property string $status
 */
class PlatformSettlementAccount extends Model
{
    // Pas de HasFactory : ces lignes se déclarent à la main depuis le registre, jamais en lot.
    use AuditsEloquentEvents;

    public const ROLE_PRIMARY = 'primary';

    public const ROLE_BACKUP = 'backup';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'label',
        'currency',
        'country',
        'bank_name',
        'holder_name',
        'iban_last4',
        'stripe_external_account_id',
        'role',
        'status',
        'verified_at',
        'activated_at',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    /**
     * Domaine d'audit : ces lignes disent où part l'argent de la plateforme. Les ranger sous
     * « finance » plutôt que sous le nom du modèle leur applique la rétention de ce domaine.
     */
    protected function auditEventDomain(): string
    {
        return 'finance';
    }

    /**
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return ['label', 'currency', 'country', 'iban_last4', 'role', 'status', 'verified_at', 'activated_at'];
    }

    /** @return BelongsTo<User, $this> */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function estPrincipal(): bool
    {
        return $this->role === self::ROLE_PRIMARY && $this->status !== self::STATUS_RETIRED;
    }

    public function estSecoursPret(): bool
    {
        return $this->role === self::ROLE_BACKUP && $this->status === self::STATUS_VERIFIED;
    }

    public function libelleMasque(): string
    {
        return $this->iban_last4 ? '•••• '.$this->iban_last4 : 'IBAN non renseigné';
    }
}
