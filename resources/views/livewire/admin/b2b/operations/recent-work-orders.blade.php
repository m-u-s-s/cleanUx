        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900">Ordres de service récents</h2>
            <div class="mt-4 space-y-3">
                @forelse($workOrders as $workOrder)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $workOrder->reference }} — {{ $workOrder->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $workOrder->organizationAccount?->name }} · {{ $workOrder->organizationSite?->name ?? 'Tous sites' }} · {{ strtoupper($workOrder->approval_status) }}</p>
                            </div>
                            <div class="flex gap-2">
                                @if($workOrder->approval_status !== 'approved')
                                    <button wire:click="approveWorkOrder({{ $workOrder->id }})" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">Approuver</button>
                                @endif
                                @if($workOrder->approval_status !== 'rejected')
                                    <button wire:click="rejectWorkOrder({{ $workOrder->id }})" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white">Rejeter</button>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 md:grid-cols-4">
                            <div>Équipe: <span class="font-semibold text-slate-900">{{ $workOrder->assignedFieldTeam?->name ?? '—' }}</span></div>
                            <div>Partenaire: <span class="font-semibold text-slate-900">{{ $workOrder->assignedServicePartner?->name ?? '—' }}</span></div>
                            <div>Budget: <span class="font-semibold text-slate-900">{{ $workOrder->budget_amount ? number_format((float) $workOrder->budget_amount, 2, ',', ' ') . ' €' : '—' }}</span></div>
                            <div>PO: <span class="font-semibold text-slate-900">{{ $workOrder->purchase_order_number ?: '—' }}</span></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aucun ordre de service pour ce compte.</p>
                @endforelse
            </div>
        </section>
    </div>
