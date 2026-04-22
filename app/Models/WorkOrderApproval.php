<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'enterprise_work_order_id',
        'approver_user_id',
        'approval_status',
        'approved_at',
        'rejected_at',
        'comment',
        'metadata',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function enterpriseWorkOrder(): BelongsTo
    {
        return $this->belongsTo(EnterpriseWorkOrder::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
