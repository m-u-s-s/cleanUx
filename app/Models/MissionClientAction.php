<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionClientAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'client_user_id',
        'action_type',
        'status',
        'message',
        'meta',
        'acted_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'acted_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}