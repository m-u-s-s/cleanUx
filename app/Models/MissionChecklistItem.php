<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_checklist_id',
        'label',
        'item_type',
        'is_required',
        'status',
        'completed_by_user_id',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(MissionChecklist::class, 'mission_checklist_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}