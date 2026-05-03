<div class="rounded-2xl border border-slate-200 p-3">
    <div class="text-xs uppercase tracking-wide text-slate-400">Contexte corporate</div>
    <div class="mt-2 space-y-1">
        <div>PO : {{ data_get($selectedRendezVous->pricing_snapshot, 'corporate_context.purchase_order_reference', '—') }}</div>
        <div>Centre de coût : {{ data_get($selectedRendezVous->pricing_snapshot, 'corporate_context.cost_center', '—') }}</div>
        <div>Échéance : {{ $selectedRendezVous->financeInvoice?->due_at?->format('d/m/Y') ?: '—' }}</div>
        <div>Dernière relance : {{ optional($selectedRendezVous->financeInvoice?->reminders?->sortByDesc('created_at')->first()?->created_at)->format('d/m/Y H:i') ?: '—' }}</div>
    </div>
</div>
