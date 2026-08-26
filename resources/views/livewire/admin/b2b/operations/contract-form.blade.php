        <section class="brio-card">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="brio-section-title">Contrat entreprise</h2>
                    <p class="brio-section-subtitle">Définit le cadre commercial, SLA, PO, équipe et partenaire par défaut.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-ui.field for="contractForm-contract_reference"
                    label="Référence contrat"
                    :error="$errors->first('contractForm.contract_reference')">
                    <input id="contractForm-contract_reference"
                        wire:model.defer="contractForm.contract_reference"
                        type="text"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.contract_reference')])>
                </x-ui.field>

                <x-ui.field for="contractForm-status"
                    label="Statut"
                    :error="$errors->first('contractForm.status')">
                    <select id="contractForm-status"
                        wire:model.defer="contractForm.status"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.status')])>
                        <option value="draft">Draft</option>
                        <option value="pilot">Pilot</option>
                        <option value="signed">Signed</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                    </select>
                </x-ui.field>

                <x-ui.field for="contractForm-provider_organization_id"
                    label="Prestataire partenaire (société)"
                    :error="$errors->first('contractForm.provider_organization_id')">
                    <select id="contractForm-provider_organization_id"
                        wire:model.defer="contractForm.provider_organization_id"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.provider_organization_id')])>
                        <option value="">Aucun</option>
                        @foreach($providerOrganizations as $providerOrg)
                            <option value="{{ $providerOrg->id }}">{{ $providerOrg->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field for="contractForm-service_zone_id"
                    label="Zone de service"
                    :error="$errors->first('contractForm.service_zone_id')">
                    <select id="contractForm-service_zone_id"
                        wire:model.defer="contractForm.service_zone_id"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.service_zone_id')])>
                        <option value="">Aucune</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field for="contractForm-approval_mode"
                    label="Mode approbation"
                    :error="$errors->first('contractForm.approval_mode')">
                    <select id="contractForm-approval_mode"
                        wire:model.defer="contractForm.approval_mode"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.approval_mode')])>
                        <option value="auto">Auto</option>
                        <option value="site_contact">Contact site</option>
                        <option value="account_owner">Responsable compte</option>
                        <option value="manual">Manuel</option>
                    </select>
                </x-ui.field>

                <x-ui.field for="contractForm-default_field_team_id"
                    label="Équipe par défaut"
                    :error="$errors->first('contractForm.default_field_team_id')">
                    <select id="contractForm-default_field_team_id"
                        wire:model.defer="contractForm.default_field_team_id"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.default_field_team_id')])>
                        <option value="">Aucune</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field for="contractForm-default_service_partner_id"
                    label="Partenaire par défaut"
                    :error="$errors->first('contractForm.default_service_partner_id')">
                    <select id="contractForm-default_service_partner_id"
                        wire:model.defer="contractForm.default_service_partner_id"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.default_service_partner_id')])>
                        <option value="">Aucun</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field for="contractForm-default_cost_center"
                    label="Coût center par défaut"
                    :error="$errors->first('contractForm.default_cost_center')">
                    <input id="contractForm-default_cost_center"
                        wire:model.defer="contractForm.default_cost_center"
                        type="text"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.default_cost_center')])>
                </x-ui.field>

                <x-ui.field for="contractForm-negotiated_discount_percent"
                    label="Remise négociée (%)"
                    :error="$errors->first('contractForm.negotiated_discount_percent')">
                    <input id="contractForm-negotiated_discount_percent"
                        wire:model.defer="contractForm.negotiated_discount_percent"
                        type="number"
                        step="0.01"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.negotiated_discount_percent')])>
                </x-ui.field>

                <div class="md:col-span-2">
                    <label for="requires-po" class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2">
                        <input
                            id="requires-po"
                            wire:model.defer="contractForm.requires_purchase_order"
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-300 text-sky-600 shadow-sm focus:ring-sky-500/20">
                        <span class="text-sm font-medium text-slate-700">Purchase order obligatoire</span>
                    </label>
                </div>

                <x-ui.field for="contractForm-notes"
                    class="md:col-span-2"
                    label="Notes"
                    :error="$errors->first('contractForm.notes')">
                    <textarea id="contractForm-notes"
                        wire:model.defer="contractForm.notes"
                        rows="3"
                        @class(['ui-input', 'ui-input-error' => $errors->has('contractForm.notes')])></textarea>
                </x-ui.field>
            </div>

            <div class="mt-6 flex justify-end">
                <button wire:click="saveContract" type="button" class="brio-btn-primary min-h-[44px]">
                    Enregistrer le contrat
                </button>
            </div>

            @if($contractId)
                <div class="mt-8 border-t border-slate-200 pt-6">
                    <div class="mb-4">
                        <h3 class="brio-section-title text-base md:text-lg">Grille tarifaire négociée</h3>
                        <p class="brio-section-subtitle">Prix unitaire négocié par service (en centimes, prioritaire sur la remise %).</p>
                    </div>

                    <x-ui.table-shell>
                        <table class="brio-table w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3">Service</th>
                                    <th class="px-4 py-3 text-right">Prix négocié</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($rateCards as $card)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-700">
                                            {{ $card->serviceCatalog?->name ?? ('Service #'.$card->service_catalog_id) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-900">
                                            {{ number_format($card->negotiated_unit_price_cents / 100, 2, ',', ' ') }} {{ $card->currency }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-6 text-center text-xs text-slate-400">
                                            Aucune grille tarifaire pour ce contrat.
                                        </td>
                                    </tr>
                                @endforelse

                                <tr class="bg-slate-50/60 align-top">
                                    <td class="px-4 py-3">
                                        <x-ui.field for="rateCardForm-service_catalog_id" label="Service">
                                            <select id="rateCardForm-service_catalog_id" wire:model.defer="rateCardForm.service_catalog_id" class="ui-input">
                                                <option value="">Sélectionner…</option>
                                                @foreach($services as $service)
                                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                                @endforeach
                                            </select>
                                        </x-ui.field>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-end">
                                            <x-ui.field for="rateCardForm-unit_price_cents" label="Prix négocié (centimes)" class="w-full sm:w-44">
                                                <input id="rateCardForm-unit_price_cents"
                                                    wire:model.defer="rateCardForm.unit_price_cents"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    class="ui-input text-right">
                                            </x-ui.field>
                                            <button
                                                type="button"
                                                wire:click="addRateCard($wire.contractId, parseInt($wire.rateCardForm.service_catalog_id), parseInt($wire.rateCardForm.unit_price_cents))"
                                                class="brio-btn-primary min-h-[44px] whitespace-nowrap">
                                                Ajouter / mettre à jour
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </x-ui.table-shell>
                </div>
            @endif
        </section>
