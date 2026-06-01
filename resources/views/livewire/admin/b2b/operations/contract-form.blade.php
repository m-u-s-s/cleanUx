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
                    <label class="mb-1 block text-sm font-medium text-slate-700">Prestataire partenaire (société)</label>
                    <select wire:model.defer="contractForm.provider_organization_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        <option value="">Aucun</option>
                        @foreach($providerOrganizations as $providerOrg)
                            <option value="{{ $providerOrg->id }}">{{ $providerOrg->name }}</option>
                        @endforeach
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

            @if($contractId)
                <div class="mt-6 border-t border-slate-200 pt-6">
                    <h3 class="text-sm font-bold text-slate-900">Grille tarifaire négociée</h3>
                    <p class="text-xs text-slate-500">Prix unitaire négocié par service (en centimes, prioritaire sur la remise %).</p>

                    @if($rateCards->isNotEmpty())
                        <ul class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                            @foreach($rateCards as $card)
                                <li class="flex items-center justify-between px-4 py-2 text-sm">
                                    <span class="font-medium text-slate-700">{{ $card->serviceCatalog?->name ?? ('Service #'.$card->service_catalog_id) }}</span>
                                    <span class="text-slate-900">{{ number_format($card->negotiated_unit_price_cents / 100, 2, ',', ' ') }} {{ $card->currency }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-xs text-slate-400">Aucune grille tarifaire pour ce contrat.</p>
                    @endif

                    <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Service</label>
                            <select wire:model.defer="rateCardForm.service_catalog_id" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                                <option value="">Sélectionner…</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Prix négocié (centimes)</label>
                            <input wire:model.defer="rateCardForm.unit_price_cents" type="number" min="0" step="1" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button
                            wire:click="addRateCard($wire.contractId, parseInt($wire.rateCardForm.service_catalog_id), parseInt($wire.rateCardForm.unit_price_cents))"
                            class="rounded-2xl bg-sky-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-sky-500">
                            Ajouter / mettre à jour la ligne
                        </button>
                    </div>
                </div>
            @endif
        </section>
