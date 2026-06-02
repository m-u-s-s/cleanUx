<?php

namespace App\Models;

use Database\Factories\MissionMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionMedia extends Model
{
    /** @use HasFactory<MissionMediaFactory> */
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'uploaded_by_user_id',
        'media_type',
        'path',
        'caption',
        'taken_at',
        'lat',
        'lng',
        'meta',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'meta' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
