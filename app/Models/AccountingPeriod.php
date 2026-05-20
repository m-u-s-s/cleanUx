<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'period_year', 'period_month',
        'is_closed', 'opened_at', 'closed_at', 'closed_by_user_id',
        'total_debit_cents', 'total_credit_cents', 'entry_count',
        'totals_by_account', 'metadata',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'total_debit_cents' => 'integer',
        'total_credit_cents' => 'integer',
        'entry_count' => 'integer',
        'totals_by_account' => 'array',
        'metadata' => 'array',
    ];

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('is_closed', false);
    }

    public function isBalanced(): bool
    {
        return $this->total_debit_cents === $this->total_credit_cents;
    }

    public function label(): string
    {
        if ($this->period_month === 0) {
            return 'Annuel ' . $this->period_year;
        }
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
