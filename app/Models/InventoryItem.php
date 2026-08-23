<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UN CONSOMMABLE EN STOCK, DANS UNE AGENCE.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int|null $provider_agency_id
 * @property string $name
 * @property string|null $sku
 * @property string $unit
 * @property int $quantity
 * @property int $reorder_threshold
 * @property int|null $unit_cost_cents
 * @property bool $is_billable
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'provider_agency_id',
        'name',
        'sku',
        'unit',
        'quantity',
        'reorder_threshold',
        'unit_cost_cents',
        'is_billable',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reorder_threshold' => 'integer',
        'unit_cost_cents' => 'integer',
        'is_billable' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    /** @return BelongsTo<ProviderAgency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(ProviderAgency::class, 'provider_agency_id');
    }

    /** Faut-il réapprovisionner ? */
    public function doitEtreReapprovisionne(): bool
    {
        return $this->is_active && $this->quantity <= $this->reorder_threshold;
    }
}
