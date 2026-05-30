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
            'service_name' => $this->resource->relationLoaded('rendezVous')
                ? (($this->rendezVous !== null ? $this->rendezVous->service_display_name : null) ?? 'Service non précisé')
                : 'Service non précisé',
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'paid_at' => optional($p->paid_at ?? $p->created_at)->toIso8601String(),
            ])),
            'reminders' => $this->whenLoaded('reminders', fn () => $this->reminders->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->reminder_type,
                'sent_at' => optional($r->sent_at ?? $r->created_at)->toIso8601String(),
                'channel' => $r->channel,
            ])),
        ];
    }
}
