        <div class="xl:col-span-2 space-y-6">
            @if($selectedCountry)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <x-kpi-card title="Pays" :value="$selectedCountry->name" tone="slate" icon="🌍" />
                    <x-kpi-card title="Readiness" :value="$selectedCountryReadinessScore.'%'" tone="blue" icon="🚀" />
                    <x-kpi-card title="Devise" :value="$selectedCountry->currency_code" tone="green" icon="💶" />
                    <x-kpi-card title="Locale" :value="$selectedCountry->default_locale" tone="amber" icon="🈯" />
                </div>

                <x-app-card title="Réglages opérationnels" subtitle="Active les briques produit par marché.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="booking_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Réservation active</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="mission_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Mission active</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="billing_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Facturation active</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="partner_network_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Réseau partenaires actif</label>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Stage marché</label>
                            <select wire:model.defer="readiness_stage" class="w-full rounded-lg border-gray-300 shadow-sm">
                                <option value="draft">Brouillon</option>
                                <option value="catalog_only">Catalogue uniquement</option>
                                <option value="booking_enabled">Réservation active</option>
                                <option value="mission_enabled">Mission active</option>
                                <option value="billing_enabled">Facturation active</option>
                                <option value="ready_for_launch">Prêt au lancement</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Symbole devise</label>
                            <input type="text" wire:model.defer="currency_symbol" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format date</label>
                            <input type="text" wire:model.defer="date_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format heure</label>
                            <input type="text" wire:model.defer="time_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format adresse</label>
                            <input type="text" wire:model.defer="address_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format téléphone</label>
                            <input type="text" wire:model.defer="phone_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Unité distance</label>
                            <input type="text" wire:model.defer="default_distance_unit" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Unité surface</label>
                            <input type="text" wire:model.defer="default_surface_unit" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Taxe par défaut (%)</label>
                            <input type="number" step="0.01" wire:model.defer="default_tax_rate" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2"><input type="checkbox" wire:model.defer="requires_vat_number_for_companies" class="rounded border-gray-300 text-blue-600 shadow-sm"> Numéro TVA obligatoire pour les entreprises</label>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveOperationalSetting" class="cu-btn-primary">Enregistrer les réglages opérationnels</button>
                    </div>
                </x-app-card>

                <x-app-card title="Facturation pays" subtitle="Préfixes, taxes, arrondis et conditions de paiement.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Préfixe devis</label><input type="text" wire:model.defer="quote_prefix" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Préfixe facture</label><input type="text" wire:model.defer="invoice_prefix" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Libellé taxe</label><input type="text" wire:model.defer="tax_label" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Paiement (jours)</label><input type="number" wire:model.defer="payment_terms_days" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Mode arrondi</label><input type="text" wire:model.defer="rounding_mode" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Séparateur décimal</label><input type="text" wire:model.defer="decimal_separator" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Séparateur milliers</label><input type="text" wire:model.defer="thousands_separator" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2"><input type="checkbox" wire:model.defer="prices_include_tax" class="rounded border-gray-300 text-blue-600 shadow-sm"> Prix saisis TTC</label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveBillingProfile" class="cu-btn-primary">Enregistrer le profil de facturation</button>
                    </div>
                </x-app-card>

                <x-app-card title="Readiness marché" subtitle="Checklist de lancement réel par pays.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-700">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="catalog_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Catalogue prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="booking_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Booking prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="mission_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Mission prête</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="billing_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Billing prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="partner_network_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Réseau partenaires prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="compliance_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Compliance prête</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="support_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Support prêt</label>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes readiness</label>
                        <textarea wire:model.defer="readiness_notes" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm"></textarea>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveReadiness" class="cu-btn-primary">Enregistrer la readiness</button>
                    </div>
                </x-app-card>

                <x-app-card title="Règles service par pays" subtitle="Ce qui est vendable et opérable sur ce marché.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service</label>
                            <select wire:model.live="service_catalog_id" class="w-full rounded-lg border-gray-300 shadow-sm">
                                @foreach($serviceCatalogs as $service)
                                    <option value="{{ $service->id }}">{{ $service->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Multiplicateur prix</label>
                            <input type="number" step="0.01" wire:model.defer="service_pricing_multiplier" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Préavis min. (h)</label><input type="number" wire:model.defer="service_minimum_notice_hours" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">SLA réponse (h)</label><input type="number" wire:model.defer="service_sla_response_hours" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">SLA résolution (h)</label><input type="number" wire:model.defer="service_sla_resolution_hours" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Équipe par défaut (optionnel)</label><input type="number" wire:model.defer="service_default_team_id" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Partenaire par défaut (optionnel)</label><input type="number" wire:model.defer="service_default_partner_id" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="service_is_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Service activé</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="service_requires_quote" class="rounded border-gray-300 text-blue-600 shadow-sm"> Devis obligatoire</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2"><input type="checkbox" wire:model.defer="service_requires_manual_validation" class="rounded border-gray-300 text-blue-600 shadow-sm"> Validation manuelle obligatoire</label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveServiceRule" class="cu-btn-primary">Enregistrer la règle service</button>
                    </div>
                </x-app-card>
            @else
                <div class="cu-card p-8 text-center text-slate-500">Sélectionne un pays pour configurer son exploitation internationale.</div>
            @endif
        </div>
