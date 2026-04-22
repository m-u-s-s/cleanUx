<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_invoice_id',
        'payment_reference',
        'provider',
        'method',
        'status',
        'amount',
        'paid_at',
        'external_reference',
        'notes',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }
}
