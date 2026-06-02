<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property ?Carbon $posting_date
 * @property int $debit_cents
 * @property int $credit_cents
 * @property float $exchange_rate
 * @property float $vat_rate
 * @property int $vat_amount_cents
 * @property array $metadata
 * @property-read int $c summed credit_cents (query alias in PeriodCloser)
 * @property-read int $d summed debit_cents (query alias in PeriodCloser)
 */
class AccountingEntry extends Model
{
    protected $fillable = [
        'entry_code', 'batch_id',
        'posting_date', 'journal_code',
        'account_code', 'account_name',
        'debit_cents', 'credit_cents',
        'label', 'reference',
        'currency', 'exchange_rate',
        'vat_rate', 'vat_amount_cents',
        'source_type', 'source_id',
        'posted_by_user_id', 'counterparty_type', 'counterparty_id',
        'metadata',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'debit_cents' => 'integer',
        'credit_cents' => 'integer',
        'exchange_rate' => 'float',
        'vat_rate' => 'float',
        'vat_amount_cents' => 'integer',
        'metadata' => 'array',
    ];

    public static function generateEntryCode(): string
    {
        return 'entry_'.Str::lower(Str::random(20));
    }

    public static function generateBatchId(): string
    {
        return 'batch_'.Str::lower(Str::random(20));
    }

    public function scopeForPeriod(Builder $q, int $year, ?int $month = null): Builder
    {
        $q->whereYear('posting_date', $year);
        if ($month) {
            $q->whereMonth('posting_date', $month);
        }

        return $q;
    }

    public function scopeForBatch(Builder $q, string $batchId): Builder
    {
        return $q->where('batch_id', $batchId);
    }

    public function scopeForAccount(Builder $q, string $accountCode): Builder
    {
        return $q->where('account_code', $accountCode);
    }

    public function amountSigned(): int
    {
        return $this->debit_cents - $this->credit_cents;
    }
}
