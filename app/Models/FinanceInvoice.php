<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithDocumentFormatting;
use Database\Factories\FinanceInvoiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Booking|null $rendezVous
 * @property-read Collection<int,FinancePayment> $payments
 * @property-read Collection<int,FinanceReminder> $reminders
 * @property string $subtotal
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $balance_due
 * @property ?Carbon $issued_at
 * @property ?Carbon $due_at
 * @property ?Carbon $paid_at
 * @property ?array $snapshot
 * @property ?array $meta
 * @property ?Carbon $billing_period_start
 * @property ?Carbon $billing_period_end
 * @property ?array $site_breakdown
 */
class FinanceInvoice extends Model
{
    /** @use HasFactory<FinanceInvoiceFactory> */
    use HasFactory;

    use InteractsWithDocumentFormatting;

    protected $fillable = [
        'rendez_vous_id',
        'finance_quote_id',
        'client_id',
        'organization_account_id',
        'invoice_number',
        'status',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'balance_due',
        'issued_at',
        'due_at',
        'paid_at',
        'snapshot',
        'meta',
        'billing_period_start',
        'billing_period_end',
        'invoice_type',
        'site_breakdown',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'snapshot' => 'array',
        'meta' => 'array',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'site_breakdown' => 'array',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    /** @return BelongsTo<FinanceQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(FinanceQuote::class, 'finance_quote_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return HasMany<FinancePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class, 'finance_invoice_id');
    }

    /** @return HasMany<FinanceReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(FinanceReminder::class, 'finance_invoice_id');
    }

    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->payments()->where('status', 'paid')->sum('amount');
        $total = (float) $this->total_amount;
        $balance = round(max($total - $paid, 0), 2);

        $status = $this->status;

        if ($balance <= 0 && $total > 0) {
            $status = 'paid';
        } elseif ($paid > 0 && $balance > 0) {
            $status = 'partial';
        } elseif ($this->due_at && now()->gt($this->due_at) && in_array($this->status, ['issued', 'sent', 'partial'], true)) {
            $status = 'overdue';
        }

        $this->forceFill([
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $balance <= 0 ? ($this->paid_at ?? now()) : null,
        ])->save();
    }
}
