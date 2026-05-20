<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTrackingPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id', 'lat', 'lng', 'accuracy_m', 'speed_mps', 'heading_deg',
        'cumulative_distance_m', 'distance_to_dest_m', 'eta_seconds',
        'client_sequence', 'recorded_at', 'created_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'accuracy_m' => 'float',
        'speed_mps' => 'float',
        'heading_deg' => 'float',
        'cumulative_distance_m' => 'integer',
        'distance_to_dest_m' => 'integer',
        'eta_seconds' => 'integer',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TripTrackingSession::class, 'session_id');
    }
}
