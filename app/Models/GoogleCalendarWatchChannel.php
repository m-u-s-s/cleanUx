<?php

namespace App\Models;

use Database\Factories\GoogleCalendarWatchChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarWatchChannel extends Model
{
    /** @use HasFactory<GoogleCalendarWatchChannelFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'connection_id',
        'channel_id',
        'resource_id',
        'calendar_id',
        'sync_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class, 'connection_id');
    }
}
