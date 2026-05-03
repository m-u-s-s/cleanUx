<div class="rounded-2xl border border-slate-200 p-3">
    <div class="text-xs uppercase tracking-wide text-slate-400">Paiement manuel</div>
    <div class="mt-2 grid gap-2">
        <input wire:model.live="manualPaymentAmount" type="number" step="0.01" min="0" class="rounded-xl border-slate-300 text-sm shadow-sm" placeholder="Montant">
        <select wire:model.live="manualPaymentMethod" class="rounded-xl border-slate-300 text-sm shadow-sm">
            <option value="manual">Manuel</option>
            <option value="bank_transfer">Virement</option>
            <option value="cash">Cash</option>
            <option value="card">Carte</option>
        </select>
        <button wire:click="recordPartialPaymentNow({{ $selectedRendezVous->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Enregistrer paiement partiel</button>
        <button wire:click="markInvoicePaidNow({{ $selectedRendezVous->id }})" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Solder la facture</button>
    </div>
</div>
