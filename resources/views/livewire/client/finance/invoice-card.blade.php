<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-200 hover:shadow-md
    {{ $invoice->status === 'overdue' ? '!border-rose-200 bg-rose-50/40' : '' }}">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-lg font-black text-slate-900">
                    {{ $invoice->invoice_number }}
                </p>

                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $this->invoiceStatusBadge((string) $invoice->status) }}">
                    {{ ucfirst((string) $invoice->status) }}
                </span>
            </div>

            <p class="mt-2 text-sm font-semibold text-slate-700">
                {{ $invoice->rendezVous?->service_display_name ?? 'Service non précisé' }}
            </p>

            <p class="mt-1 text-sm text-slate-500">
                Émise le {{ optional($invoice->issued_at)->format('d/m/Y') ?: '—' }}
                · Échéance {{ optional($invoice->due_at)->format('d/m/Y') ?: '—' }}
            </p>

            <p class="mt-1 text-sm text-slate-500">
                Reste à payer :
                <span class="font-bold {{ (float) $invoice->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ $invoice->formatDocumentMoney($invoice->balance_due) }}
                </span>
            </p>

            @if($invoice->status === 'overdue')
                <p class="mt-2 text-xs font-bold text-rose-700">
                    ⚠️ Cette facture est en retard.
                </p>
            @endif
        </div>

        <div class="flex flex-col items-start gap-3 lg:items-end">
            <p class="text-2xl font-black text-slate-900">
                {{ $invoice->formatDocumentMoney($invoice->total_amount) }}
            </p>

            <a href="{{ route('client.finance.invoice.download', $invoice) }}" class="cu-btn-secondary">
                📥 Télécharger
            </a>
        </div>
    </div>
</div>
