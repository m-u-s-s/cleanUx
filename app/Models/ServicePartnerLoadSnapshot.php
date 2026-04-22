<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePartnerLoadSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_partner_id',
        'snapshot_date',
        'active_missions_count',
        'planned_segments_count',
        'planned_minutes',
        'daily_capacity',
        'utilization_percent',
        'metadata',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'active_missions_count' => 'integer',
        'planned_segments_count' => 'integer',
        'planned_minutes' => 'integer',
        'daily_capacity' => 'integer',
        'utilization_percent' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }
}
