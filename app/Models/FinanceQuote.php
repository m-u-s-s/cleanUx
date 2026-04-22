<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithDocumentFormatting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinanceQuote extends Model
{
    use HasFactory;
    use InteractsWithDocumentFormatting;

    protected $fillable = [
        'rendez_vous_id',
        'client_id',
        'organization_account_id',
        'quote_number',
        'status',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'issued_at',
        'valid_until',
        'accepted_at',
        'snapshot',
        'meta',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'valid_until' => 'datetime',
        'accepted_at' => 'datetime',
        'snapshot' => 'array',
        'meta' => 'array',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(FinanceInvoice::class, 'finance_quote_id');
    }
}
