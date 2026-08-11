<?php

namespace App\Models;

use Database\Factories\ProviderQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * UN DEVIS QUE LA SOCIÉTÉ BÂTIT ELLE-MÊME (E24).
 *
 * « Je passe voir, je chiffre, je vous envoie ça » est le geste le plus ordinaire d'une société de
 * services, et il n'existait que dans l'écran d'administration de la plateforme — saisi à la main
 * par un opérateur, pour le compte de la société.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int|null $client_user_id
 * @property string $reference
 * @property string $title
 * @property string $status
 * @property int $total_cents
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class ProviderQuote extends Model
{
    /** @use HasFactory<ProviderQuoteFactory> */
    use HasFactory;

    /** Modifiable, invisible du client. */
    public const STATUS_DRAFT = 'draft';

    /** Envoyé : le montant est FIGÉ, et le client peut répondre. */
    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    /** Conservé : un refus qu'on efface, c'est une négociation qui recommence de zéro. */
    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_account_id',
        'client_user_id',
        'client_organization_id',
        'organization_site_id',
        'reference',
        'title',
        'intro',
        'status',
        'created_by_user_id',
        'total_cents',
        'currency',
        'tax_rate',
        'valid_until',
        'sent_at',
        'decided_at',
        'decision_note',
        'metadata',
    ];

    protected $casts = [
        'total_cents' => 'integer',
        'tax_rate' => 'decimal:2',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function genererUneReference(): string
    {
        return 'DEV-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
    }

    /** @return HasMany<ProviderQuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProviderQuoteLine::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /**
     * Le devis attend-il encore une réponse du client ?
     *
     * L'ÉCHÉANCE COMPTE MÊME SI LE BALAYAGE N'EST PAS PASSÉ. Un devis périmé dont le statut dit
     * encore `sent` ne doit pas pouvoir être accepté au prix d'il y a trois mois — sans quoi la
     * validité dépendrait de l'heure du cron.
     */
    public function estOuvert(): bool
    {
        return $this->status === self::STATUS_SENT
            && ($this->valid_until === null || $this->valid_until->endOfDay()->isFuture());
    }
}
