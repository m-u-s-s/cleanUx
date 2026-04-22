<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleCalendarConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'google_email',
        'google_user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'scope',
        'sync_enabled',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
        'meta',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'sync_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLinks(): HasMany
    {
        return $this->hasMany(GoogleCalendarEventLink::class);
    }

    public function tokenExpired(): bool
    {
        return ! $this->token_expires_at || $this->token_expires_at->isPast();
    }
}
