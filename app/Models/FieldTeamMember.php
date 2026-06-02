<?php

namespace App\Models;

use Database\Factories\FieldTeamMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTeamMember extends Model
{
    /** @use HasFactory<FieldTeamMemberFactory> */
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

    /** @return BelongsTo<FieldTeam, $this> */
    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
