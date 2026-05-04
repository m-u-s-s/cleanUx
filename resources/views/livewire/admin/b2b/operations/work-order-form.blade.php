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
