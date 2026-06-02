<?php

namespace App\Models;

use Database\Factories\WorkOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderLine extends Model
{
    /** @use HasFactory<WorkOrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'enterprise_work_order_id',
        'service_catalog_id',
        'title',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'agreed_unit_price',
        'line_total',
        'surface_value',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'agreed_unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'surface_value' => 'decimal:2',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<EnterpriseWorkOrder, $this> */
    public function enterpriseWorkOrder(): BelongsTo
    {
        return $this->belongsTo(EnterpriseWorkOrder::class);
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}
