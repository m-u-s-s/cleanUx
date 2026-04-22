<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'actor_user_id',
        'event_type',
        'title',
        'description',
        'payload',
        'happened_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'happened_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}