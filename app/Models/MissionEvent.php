<?php

namespace App\Models;

use Database\Factories\MissionEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionEvent extends Model
{
    /** @use HasFactory<MissionEventFactory> */
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'actor_user_id',
        'event_type',
        'title',
        'description',
        'payload',
        'happened_at',

        // Écrite par le code, écartée par Eloquent faute de figurer ici.
        'user_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'happened_at' => 'datetime',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
