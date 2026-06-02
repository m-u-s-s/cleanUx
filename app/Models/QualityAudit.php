<?php

namespace App\Models;

use Database\Factories\QualityAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityAudit extends Model
{
    /** @use HasFactory<QualityAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'rendez_vous_id',
        'employe_id',
        'service_zone_id',
        'auditor_id',
        'score',
        'punctuality_score',
        'service_score',
        'communication_score',
        'checklist',
        'attachment_evidence',
        'notes',
        'action_plan',
        'follow_up_required',
        'follow_up_due_at',
        'status',
        'audited_at',
        'closed_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'attachment_evidence' => 'array',
        'follow_up_required' => 'boolean',
        'follow_up_due_at' => 'datetime',
        'audited_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    /** @return BelongsTo<User, $this> */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    /** @return BelongsTo<User, $this> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
