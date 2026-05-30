<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->invoice_number,
            'amount' => (float) $this->total_amount,
            'balance_due' => (float) $this->balance_due,
            'currency' => strtoupper((string) ($this->currency ?? 'EUR')),
            'status' => $this->status,
            // FinanceInvoice has no effectiveStatus() method; status is the source of truth.
            'effective_status' => $this->status,
            'issued_at' => optional($this->issued_at)->toIso8601String(),
            'due_at' => optional($this->due_at)->toIso8601String(),
        ];
    }
}
