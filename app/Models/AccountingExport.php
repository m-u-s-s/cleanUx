<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccountingExport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'code', 'format',
        'period_year', 'period_month',
        'status', 'file_path', 'file_size_bytes', 'file_hash',
        'row_count', 'generated_by_user_id', 'generated_at', 'expires_at',
        'last_error', 'metadata',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'file_size_bytes' => 'integer',
        'row_count' => 'integer',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'exp_' . Str::lower(Str::random(20));
    }

    public function scopeReady(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_READY);
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }
}
