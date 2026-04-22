<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarEventLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_calendar_connection_id',
        'rendez_vous_id',
        'google_event_id',
        'google_calendar_id',
        'etag',
        'last_synced_at',
        'sync_status',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class, 'google_calendar_connection_id');
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }
}
