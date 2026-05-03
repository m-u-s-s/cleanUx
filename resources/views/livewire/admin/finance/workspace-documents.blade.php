<div class="rounded-2xl border border-slate-200 p-3">
    <div class="text-xs uppercase tracking-wide text-slate-400">Documents</div>
    <div class="mt-2 space-y-1">
        <div>Devis : <span class="font-medium text-slate-800">{{ $selectedRendezVous->financeQuote?->quote_number ?: '—' }}</span></div>
        <div>Facture : <span class="font-medium text-slate-800">{{ $selectedRendezVous->financeInvoice?->invoice_number ?: '—' }}</span></div>
        <div>Statut facture : <span class="font-medium text-slate-800">{{ $selectedRendezVous->financeInvoice?->status ?: '—' }}</span></div>
        <div>Solde : <span class="font-medium text-slate-800">€ {{ number_format((float) ($selectedRendezVous->financeInvoice?->balance_due ?? 0), 2, ',', ' ') }}</span></div>
    </div>
</div>
