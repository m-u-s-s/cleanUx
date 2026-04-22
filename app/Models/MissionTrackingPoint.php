<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionTrackingPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_session_id',
        'recorded_at',
        'lat',
        'lng',
        'accuracy_meters',
        'speed_kmh',
        'heading',
        'battery_level',
        'source',
        'app_state',
        'meta',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'accuracy_meters' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'heading' => 'decimal:2',
        'battery_level' => 'integer',
        'meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(MissionTrackingSession::class, 'tracking_session_id');
    }
}