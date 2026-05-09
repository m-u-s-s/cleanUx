@if($cancelRdvId)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Confirmer l’annulation</h3>
            <p class="mt-1 text-sm text-slate-500">Ajoute une raison si tu veux garder une trace côté support.</p>
        </div>

        <textarea
            wire:model.defer="cancelReason"
            rows="4"
            placeholder="Raison d’annulation (facultatif)..."
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"></textarea>

        // Avant de confirmer cancel, fetch le quote
        const quote = await fetch(`/api/client/bookings/${bookingId}/cancellation-quote`, {
        headers: { 'Authorization': `Bearer ${token}` },
        }).then(r => r.json());

        if (quote.quote.fee_amount > 0) {
        // Afficher modale "Tu seras facturé X€"
        confirm(`L'annulation entraînera des frais de ${quote.quote.fee_amount}€. Continuer ?`);
        }

        // Si confirmé
        await fetch(`/api/client/bookings/${bookingId}/cancel-with-fee`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason: 'changement de plans', accept_fee: true }),
        });
        <div class="flex flex-wrap justify-end gap-3">
            <button type="button" wire:click="fermerAnnulation" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Retour</button>
            <button type="button" wire:click="confirmerAnnulation" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white">Confirmer l’annulation</button>
        </div>
    </div>
</div>
@endif