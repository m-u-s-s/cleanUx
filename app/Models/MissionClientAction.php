<?php

namespace App\Models;

use Database\Factories\MissionClientActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionClientAction extends Model
{
    /** @use HasFactory<MissionClientActionFactory> */
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

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}
