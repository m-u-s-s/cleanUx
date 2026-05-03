<div class="grid gap-2 pt-2">
    <button wire:click="downloadQuotePdf({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Télécharger devis</button>
    <button wire:click="downloadInvoicePdf({{ $selectedRendezVous->id }})" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Télécharger facture</button>
</div>
