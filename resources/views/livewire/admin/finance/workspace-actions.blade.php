<div class="grid gap-2">
    <button wire:click="ensureQuoteDocument({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sync devis</button>
    <button wire:click="ensureInvoiceDocument({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sync facture</button>
    <button wire:click="issueInvoiceNow({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Émettre facture</button>
    <button wire:click="sendInvoiceReminderNow({{ $selectedRendezVous->id }}, 'gentle')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Relance douce</button>
    <button wire:click="sendInvoiceReminderNow({{ $selectedRendezVous->id }}, 'overdue')" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700">Relance retard</button>
</div>
