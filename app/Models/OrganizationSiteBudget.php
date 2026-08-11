<?php

namespace App\Models;

use Database\Factories\OrganizationSiteBudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UN PLAFOND DE DÉPENSE POUR UNE PÉRIODE (E7).
 *
 * LE PLAFOND ALERTE, IL NE BLOQUE PAS. Une intervention refusée parce qu'un budget mensuel est
 * atteint, c'est une fuite d'eau qu'on laisse couler pour une ligne comptable.
 *
 * `organization_site_id` À NULL = TOUTE LA SOCIÉTÉ. C'est le premier budget que la plupart poseront,
 * avant de descendre au local.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int|null $organization_site_id
 * @property string $period
 * @property \Illuminate\Support\Carbon $period_start
 * @property int $limit_cents
 * @property int $alert_threshold_percent
 * @property \Illuminate\Support\Carbon|null $alerted_at
 * @property int|null $alerted_at_percent
 */
class OrganizationSiteBudget extends Model
{
    /** @use HasFactory<OrganizationSiteBudgetFactory> */
    use HasFactory;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_QUARTERLY = 'quarterly';

    protected $fillable = [
        'organization_account_id',
        'organization_site_id',
        'period',
        'period_start',
        'limit_cents',
        'currency',
        'alert_threshold_percent',
        'alerted_at',
        'alerted_at_percent',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'limit_cents' => 'integer',
        'alert_threshold_percent' => 'integer',
        'alerted_at' => 'datetime',
        'alerted_at_percent' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<OrganizationSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** Le dernier jour couvert — bornes INCLUSIVES, comme un relevé de compte. */
    public function finDePeriode(): Carbon
    {
        return $this->period === self::PERIOD_QUARTERLY
            ? $this->period_start->copy()->addMonths(3)->subDay()->endOfDay()
            : $this->period_start->copy()->addMonth()->subDay()->endOfDay();
    }

    public function couvre(Carbon $moment): bool
    {
        return $moment->betweenIncluded($this->period_start->copy()->startOfDay(), $this->finDePeriode());
    }
}
