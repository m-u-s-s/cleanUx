    @if($selectedApprovalId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Refuser la demande</h3>
                    <p class="text-sm text-slate-500">
                        Ajoutez une raison claire pour garder une trace.
                    </p>
                </div>

                <textarea
                    wire:model.defer="rejectionReason"
                    rows="4"
                    class="w-full rounded-xl border-gray-300 text-sm"
                    placeholder="Raison du refus..."></textarea>

                @error('rejectionReason')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-3">
                    <button
                        wire:click="closeRejectModal"
                        class="rounded-xl border px-4 py-2 text-sm font-medium text-slate-700">
                        Annuler
                    </button>

                    <button
                        wire:click="reject"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white">
                        Confirmer le refus
                    </button>
                </div>
            </div>
        </div>
    @endif
