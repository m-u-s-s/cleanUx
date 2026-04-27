<x-page-shell
    title="🏢 Approbations entreprises"
    subtitle="Validez les demandes B2B avant confirmation définitive du rendez-vous.">

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Client, entreprise, site..."
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Statut</label>
                <select wire:model.live="status" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">Tous</option>
                    <option value="pending_manager">En attente manager</option>
                    <option value="pending_finance">En attente finance</option>
                    <option value="approved">Approuvé</option>
                    <option value="rejected">Refusé</option>
                    <option value="cancelled">Annulé</option>
                </select>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        @forelse($approvals as $approval)
            <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-900">
                            {{ $approval->organizationAccount?->name ?? 'Entreprise inconnue' }}
                        </h3>

                        <p class="text-sm text-slate-500">
                            Site : {{ $approval->organizationSite?->name ?? '—' }}
                        </p>

                        <p class="text-sm text-slate-500">
                            Client : {{ $approval->rendezVous?->client?->name ?? '—' }}
                        </p>

                        <p class="text-sm text-slate-500">
                            Service : {{ $approval->rendezVous?->service_display_name ?? '—' }}
                        </p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-medium
                        @class([
                            'bg-amber-100 text-amber-700' => in_array($approval->status, ['pending_manager', 'pending_finance']),
                            'bg-emerald-100 text-emerald-700' => $approval->status === 'approved',
                            'bg-red-100 text-red-700' => $approval->status === 'rejected',
                            'bg-slate-100 text-slate-700' => $approval->status === 'cancelled',
                        ])">
                        {{ $approval->status_label }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-xl border bg-slate-50 p-3">
                        <p class="text-slate-500">Date RDV</p>
                        <p class="font-medium text-slate-900">
                            {{ $approval->rendezVous?->date?->format('d/m/Y') ?? '—' }}
                            {{ $approval->rendezVous?->heure ? 'à '.substr((string) $approval->rendezVous->heure, 0, 5) : '' }}
                        </p>
                    </div>

                    <div class="rounded-xl border bg-slate-50 p-3">
                        <p class="text-slate-500">Devis</p>
                        <p class="font-medium text-slate-900">
                            {{ number_format((float) ($approval->rendezVous?->devis_estime ?? 0), 2, ',', ' ') }} €
                        </p>
                    </div>

                    <div class="rounded-xl border bg-slate-50 p-3">
                        <p class="text-slate-500">Manager</p>
                        <p class="font-medium text-slate-900">
                            {{ $approval->managerApprovedBy?->name ?? '—' }}
                        </p>
                    </div>

                    <div class="rounded-xl border bg-slate-50 p-3">
                        <p class="text-slate-500">Finance</p>
                        <p class="font-medium text-slate-900">
                            {{ $approval->financeApprovedBy?->name ?? '—' }}
                        </p>
                    </div>
                </div>

                @if($approval->request_note || $approval->manager_note || $approval->finance_note || $approval->rejection_reason)
                    <div class="rounded-xl border bg-slate-50 p-4 text-sm space-y-2">
                        @if($approval->request_note)
                            <p><span class="font-medium">Note demande :</span> {{ $approval->request_note }}</p>
                        @endif

                        @if($approval->manager_note)
                            <p><span class="font-medium">Note manager :</span> {{ $approval->manager_note }}</p>
                        @endif

                        @if($approval->finance_note)
                            <p><span class="font-medium">Note finance :</span> {{ $approval->finance_note }}</p>
                        @endif

                        @if($approval->rejection_reason)
                            <p class="text-red-700"><span class="font-medium">Raison refus :</span> {{ $approval->rejection_reason }}</p>
                        @endif
                    </div>
                @endif

                @if(in_array($approval->status, ['pending_manager', 'pending_finance']))
                    <div class="rounded-xl border bg-white p-4 space-y-3">
                        <textarea
                            wire:model.defer="note"
                            rows="2"
                            placeholder="Note interne facultative..."
                            class="w-full rounded-xl border-gray-300 text-sm"></textarea>

                        <div class="flex flex-wrap gap-3">
                            @if($approval->status === 'pending_manager')
                                <button
                                    wire:click="approveManager({{ $approval->id }})"
                                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                    ✅ Valider manager
                                </button>
                            @endif

                            @if($approval->status === 'pending_finance')
                                <button
                                    wire:click="approveFinance({{ $approval->id }})"
                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                    💰 Valider finance
                                </button>
                            @endif

                            <button
                                wire:click="openRejectModal({{ $approval->id }})"
                                class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                ❌ Refuser
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state
                title="Aucune approbation"
                message="Aucune demande entreprise ne correspond aux filtres." />
        @endforelse
    </div>

    <div>
        {{ $approvals->links() }}
    </div>

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
</x-page-shell>