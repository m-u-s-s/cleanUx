<?php

namespace App\Models;

use Database\Factories\FinanceCreditNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avoir (credit note) émis lors d'un remboursement client. Document comptable
 * numéroté, daté, référençant la prestation/facture d'origine (conformité BE/FR).
 */
class FinanceCreditNote extends Model
{
    /** @use HasFactory<FinanceCreditNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'credit_note_number',
        'booking_id',
        'finance_invoice_id',
        'client_id',
        'organization_account_id',
        'amount',
        'tax_rate',
        'tax_amount',
        'currency',
        'reason',
        'stripe_refund_id',
        'issued_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'meta' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
