<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class MissionVerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'code_type',
        'code_hash',
        'expires_at',
        'validated_by_user_id',
        'validated_at',
        'attempts',
        'is_consumed',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'validated_at' => 'datetime',
        'is_consumed' => 'boolean',
        'attempts' => 'integer',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function setCodeTypeAttribute($value): void
    {
        if (Schema::hasColumn($this->getTable(), 'code_type')) {
            $this->attributes['code_type'] = $value;
        }

        if (Schema::hasColumn($this->getTable(), 'type')) {
            $this->attributes['type'] = $value;
        }
    }

    public function setTypeAttribute($value): void
    {
        if (Schema::hasColumn($this->getTable(), 'type')) {
            $this->attributes['type'] = $value;
        }

        if (Schema::hasColumn($this->getTable(), 'code_type')) {
            $this->attributes['code_type'] = $value;
        }
    }
}
