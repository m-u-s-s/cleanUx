<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_team_id',
        'user_id',
        'role_on_team',
        'is_team_lead',
        'is_active',
        'joined_at',
        'left_at',
        'metadata',
    ];

    protected $casts = [
        'is_team_lead' => 'boolean',
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
