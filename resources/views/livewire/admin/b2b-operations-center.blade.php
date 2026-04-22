<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">B2B lourd</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Centre opérations entreprises</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Contrats explicites, ordres de service, validations et affectations d’équipes/partenaires pour les comptes complexes.</p>
            </div>
            <div class="min-w-[260px]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Compte entreprise</label>
                <select wire:model.live="selectedAccountId" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Sélectionner</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->organization_contracts_count }} contrats / {{ $account->enterprise_work_orders_count }} OS)</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Contrat entreprise</h2>
                    <p class="text-sm text-slate-500">Définit le cadre commercial, SLA, PO, équipe et partenaire par défaut.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Référence contrat</label>
                    <input wire:model.defer="contractForm.contract_reference" type="text" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Statut</label>
                    <select wire:model.defer="contractForm.status" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="draft">Draft</option>
                        <option value="pilot">Pilot</option>
                        <option value="signed">Signed</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Zone de service</label>
                    <select wire:model.defer="contractForm.service_zone_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucune</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Mode approbation</label>
                    <select wire:model.defer="contractForm.approval_mode" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="auto">Auto</option>
                        <option value="site_contact">Contact site</option>
                        <option value="account_owner">Responsable compte</option>
                        <option value="manual">Manuel</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Équipe par défaut</label>
                    <select wire:model.defer="contractForm.default_field_team_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucune</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Partenaire par défaut</label>
                    <select wire:model.defer="contractForm.default_service_partner_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucun</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Coût center par défaut</label>
                    <input wire:model.defer="contractForm.default_cost_center" type="text" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Remise négociée (%)</label>
                    <input wire:model.defer="contractForm.negotiated_discount_percent" type="number" step="0.01" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div class="md:col-span-2 flex items-center gap-3">
                    <input id="requires-po" wire:model.defer="contractForm.requires_purchase_order" type="checkbox" class="rounded border-slate-300 text-sky-600 shadow-sm">
                    <label for="requires-po" class="text-sm font-medium text-slate-700">Purchase order obligatoire</label>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                    <textarea wire:model.defer="contractForm.notes" rows="3" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm"></textarea>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button wire:click="saveContract" class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-slate-800">Enregistrer le contrat</button>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-slate-900">Ordre de service entreprise</h2>
                <p class="text-sm text-slate-500">Demandes lourdes, multisites, chantier, bureau ou intervention complexe avec approbation et budget.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Titre</label>
                    <input wire:model.defer="workOrderForm.title" type="text" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Référence</label>
                    <input wire:model.defer="workOrderForm.reference" type="text" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Type</label>
                    <select wire:model.defer="workOrderForm.work_type" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="site_intervention">Site intervention</option>
                        <option value="chantier">Chantier</option>
                        <option value="office_program">Programme bureaux</option>
                        <option value="deep_clean">Deep clean</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Service</label>
                    <select wire:model.defer="workOrderForm.service_catalog_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucun</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Zone</label>
                    <select wire:model.defer="workOrderForm.service_zone_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucune</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Équipe assignée</label>
                    <select wire:model.defer="workOrderForm.assigned_field_team_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucune</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Partenaire assigné</label>
                    <select wire:model.defer="workOrderForm.assigned_service_partner_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucun</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Statut approbation</label>
                    <select wire:model.defer="workOrderForm.approval_status" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Priorité</label>
                    <select wire:model.defer="workOrderForm.priority" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="normale">Normale</option>
                        <option value="haute">Haute</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">PO</label>
                    <input wire:model.defer="workOrderForm.purchase_order_number" type="text" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Cost center</label>
                    <input wire:model.defer="workOrderForm.cost_center" type="text" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Budget</label>
                    <input wire:model.defer="workOrderForm.budget_amount" type="number" step="0.01" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Instructions</label>
                    <textarea wire:model.defer="workOrderForm.instructions" rows="3" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm"></textarea>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Lignes d’ordre de service</h3>
                    <button wire:click="addWorkOrderLine" type="button" class="rounded-xl border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700">+ Ajouter ligne</button>
                </div>
                <div class="space-y-3">
                    @foreach($workOrderLines as $index => $line)
                        <div class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-3 md:grid-cols-6">
                            <div class="md:col-span-2">
                                <input wire:model.defer="workOrderLines.{{ $index }}.title" type="text" placeholder="Ligne / lot / zone" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                            </div>
                            <div>
                                <input wire:model.defer="workOrderLines.{{ $index }}.quantity" type="number" step="0.1" placeholder="Qté" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                            </div>
                            <div>
                                <input wire:model.defer="workOrderLines.{{ $index }}.unit" type="text" placeholder="Unité" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                            </div>
                            <div>
                                <input wire:model.defer="workOrderLines.{{ $index }}.unit_price" type="number" step="0.01" placeholder="PU" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <input wire:model.defer="workOrderLines.{{ $index }}.surface_value" type="number" step="0.01" placeholder="Surface" class="w-full rounded-xl border-slate-300 text-sm shadow-sm">
                                <button wire:click="removeWorkOrderLine({{ $index }})" type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600">Suppr.</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button wire:click="saveWorkOrder" class="rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sky-700">Enregistrer l’ordre de service</button>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900">Contrats récents</h2>
            <div class="mt-4 space-y-3">
                @forelse($contracts as $contract)
                    <button wire:click="loadContract({{ $contract->id }})" class="w-full rounded-2xl border border-slate-200 p-4 text-left hover:border-sky-300 hover:bg-sky-50/50">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $contract->contract_reference }}</p>
                                <p class="text-xs text-slate-500">{{ $contract->organizationAccount?->name }} · {{ strtoupper($contract->status) }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                <div>Équipe: {{ $contract->defaultFieldTeam?->name ?? '—' }}</div>
                                <div>Partenaire: {{ $contract->defaultServicePartner?->name ?? '—' }}</div>
                            </div>
                        </div>
                    </button>
                @empty
                    <p class="text-sm text-slate-500">Aucun contrat pour ce compte.</p>
                @endforelse
            </div>
        </section>

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
</div>
