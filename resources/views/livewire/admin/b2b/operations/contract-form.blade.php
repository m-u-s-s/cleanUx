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
