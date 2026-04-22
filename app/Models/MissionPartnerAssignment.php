<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionPartnerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'service_partner_id',
        'assignment_status',
        'assigned_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'agreed_rate',
        'sla_snapshot',
        'instructions_snapshot',
        'metadata',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'agreed_rate' => 'decimal:2',
        'sla_snapshot' => 'array',
        'instructions_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }
}
