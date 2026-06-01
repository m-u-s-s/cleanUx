<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractRateCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_contract_id',
        'service_catalog_id',
        'negotiated_unit_price_cents',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'negotiated_unit_price_cents' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<OrganizationContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}
