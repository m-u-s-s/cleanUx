<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'reported_by_user_id',
        'resolved_by_user_id',
        'incident_type',
        'severity',
        'status',
        'title',
        'description',
        'resolution_notes',
        'client_visible',
        'reported_at',
        'resolved_at',
        'meta',
    ];

    protected $casts = [
        'client_visible' => 'boolean',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}