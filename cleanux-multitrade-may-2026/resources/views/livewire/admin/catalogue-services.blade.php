<div class="cu-page">
    <x-page-shell
        eyebrow="Pilotage admin"
        title="Catalogue services"
        subtitle="Gère les services vendables, leurs paramètres métier et leurs règles par zone."
    >
        <x-slot name="actions">
            <button wire:click="resetServiceForm" class="cu-btn-secondary">Nouveau service</button>
            @if($selectedService)
                <span class="cu-chip !border-sky-200 !bg-sky-50 !text-sky-700">Service sélectionné : {{ $selectedService->name }}</span>
            @endif
        </x-slot>
    </x-page-shell>

    @if (session()->has('success'))
        <div class="cu-note !border-emerald-200 !bg-emerald-50 !text-emerald-700">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card title="Services visibles" :value="$services->total()" tone="blue" icon="🧾" />
        <x-kpi-card title="Types distincts" :value="count($serviceTypes)" tone="slate" icon="🧩" />
        <x-kpi-card title="Actifs" :value="$services->getCollection()->where('is_active', true)->count()" tone="green" icon="✅" />
        <x-kpi-card title="Entreprise" :value="$services->getCollection()->where('is_entreprise', true)->count()" tone="amber" icon="🏢" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-app-card class="xl:col-span-1" padding="p-5 md:p-6" title="Créer / modifier" subtitle="Définissez les paramètres clés du service sélectionné.">
            <div class="cu-form-grid xl:grid-cols-1">
                <div>
                    <label class="cu-field-label">Code</label>
                    <input wire:model.defer="code" type="text">
                    @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="cu-field-label">Nom</label>
                    <input wire:model.live="name" type="text">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="cu-field-label">Slug</label>
                    <input wire:model.defer="slug" type="text">
                    @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="cu-field-label">Type</label>
                    <input wire:model.defer="service_type" type="text" placeholder="standard, premium...">
                    @error('service_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Phase 1 — Métier (Trade). Optionnel pendant la transition,
                     destiné à devenir requis quand toute la base sera backfillée. --}}
                <div>
                    <label class="cu-field-label">Métier</label>
                    <select wire:model.defer="trade_id" class="w-full">
                        <option value="">— Aucun (à rattacher) —</option>
                        @foreach($trades as $trade)
                            <option value="{{ $trade->id }}">{{ $trade->name }}</option>
                        @endforeach
                    </select>
                    @error('trade_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">
                        Rattache ce service à un corps de métier (Nettoyage, Peinture, Bâtiment...).
                        Utilisé par la réservation client pour grouper les services.
                    </p>
                </div>

                <div>
                    <label class="cu-field-label">Durée par défaut (min)</label>
                    <input wire:model.defer="default_duration_minutes" type="number" min="15">
                    @error('default_duration_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="cu-field-label">Prix de base</label>
                    <input wire:model.defer="base_price" type="number" step="0.01" min="0">
                    @error('base_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="cu-field-label">Ordre</label>
                    <input wire:model.defer="sort_order" type="number" min="0">
                </div>

                <div class="md:col-span-2 xl:col-span-1">
                    <label class="cu-field-label">Description</label>
                    <textarea wire:model.defer="description" rows="4"></textarea>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <label class="cu-choice-card !items-center"><input wire:model.defer="is_active" type="checkbox"> <span>Actif</span></label>
                <label class="cu-choice-card !items-center"><input wire:model.defer="requires_quote" type="checkbox"> <span>Sur devis</span></label>
                <label class="cu-choice-card !items-center"><input wire:model.defer="requires_manual_validation" type="checkbox"> <span>Validation manuelle</span></label>
                <label class="cu-choice-card !items-center"><input wire:model.defer="is_entreprise" type="checkbox"> <span>Entreprise</span></label>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <button wire:click="saveService" class="cu-btn-primary">Enregistrer le service</button>
                <button wire:click="resetServiceForm" class="cu-btn-secondary">Réinitialiser</button>
            </div>
        </x-app-card>

        <div class="xl:col-span-2 space-y-6">
            <div class="cu-filter-panel space-y-4">
                <div class="cu-toolbar gap-3">
                    <div>
                        <h3 class="cu-section-title">Filtres catalogue</h3>
                        <p class="cu-section-subtitle">Recherchez rapidement les services et segmentez par type, statut ou marché.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche nom, code, slug...">

                    <select wire:model.live="status">
                        <option value="">Tous statuts</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Inactifs</option>
                    </select>

                    <select wire:model.live="market">
                        <option value="">Tous marchés</option>
                        <option value="standard">Particuliers</option>
                        <option value="entreprise">Entreprises</option>
                    </select>

                    <select wire:model.live="serviceType">
                        <option value="">Tous types</option>
                        @foreach($serviceTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>

                    {{-- Phase 1 — Filtre par métier --}}
                    <select wire:model.live="tradeFilter">
                        <option value="">Tous métiers</option>
                        @foreach($trades as $trade)
                            <option value="{{ $trade->id }}">{{ $trade->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div wire:loading.delay.short wire:target="search,status,market,serviceType,tradeFilter,selectService,toggleActive" class="cu-loading-panel space-y-3">
                <x-skeleton-block height="h-5" width="w-56" />
                <x-skeleton-block height="h-14" />
                <x-skeleton-block height="h-14" />
                <x-skeleton-block height="h-14" />
            </div>

            <div wire:loading.remove wire:target="search,status,market,serviceType,tradeFilter,selectService,toggleActive">
                <x-table-shell title="Services" subtitle="Vue pilotable de l’offre commerciale active et inactive.">
                    <table class="min-w-full cu-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Métier</th>
                                <th>Type</th>
                                <th>Prix</th>
                                <th>Durée</th>
                                <th>Marché</th>
                                <th>Statut</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($services as $service)
                                <tr class="{{ $selectedService?->id === $service->id ? 'bg-sky-50/70' : '' }}">
                                    <td>
                                        <div class="font-semibold text-slate-900">{{ $service->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $service->code }} · {{ $service->slug }}</div>
                                    </td>
                                    <td>
                                        @if($service->trade)
                                            <span class="cu-chip !border-blue-200 !bg-blue-50 !text-blue-700">{{ $service->trade->name }}</span>
                                        @else
                                            <span class="cu-chip !border-amber-200 !bg-amber-50 !text-amber-700">Non rattaché</span>
                                        @endif
                                    </td>
                                    <td>{{ $service->service_type }}</td>
                                    <td>€ {{ number_format((float) $service->base_price, 2, ',', ' ') }}</td>
                                    <td>{{ $service->default_duration_minutes }} min</td>
                                    <td>
                                        @if($service->is_entreprise)
                                            <span class="cu-chip !border-purple-200 !bg-purple-50 !text-purple-700">Entreprise</span>
                                        @else
                                            <span class="cu-chip">Particulier</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($service->is_active)
                                            <span class="cu-chip !border-emerald-200 !bg-emerald-50 !text-emerald-700">Actif</span>
                                        @else
                                            <span class="cu-chip !border-red-200 !bg-red-50 !text-red-700">Inactif</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button wire:click="selectService({{ $service->id }})" class="cu-btn-secondary">Gérer</button>
                                            <button wire:click="toggleActive({{ $service->id }})" class="cu-btn-secondary">Basculer</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-empty-state title="Aucun service trouvé" message="Ajustez les filtres ou créez une nouvelle entrée dans le catalogue." icon="🧾" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-table-shell>

                <div class="mt-4">
                    {{ $services->links() }}
                </div>
            </div>

            @if($selectedService)
                <x-app-card padding="p-5 md:p-6" title="Règles par zone · {{ $selectedService->name }}" subtitle="Ajustez l’activation, la tarification et la validation zone par zone.">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div>
                            <label class="cu-field-label">Zone</label>
                            <select wire:model.live="selectedZoneId">
                                <option value="">Choisir une zone</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }} @if($zone->province) · {{ $zone->province->name }} @endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-2 flex items-end">
                            @if($selectedZoneId)
                                <button wire:click="editZoneRule({{ (int) $selectedZoneId }})" class="cu-btn-secondary">Charger la règle</button>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 cu-form-grid xl:grid-cols-3">
                        <div>
                            <label class="cu-field-label">Prix spécifique</label>
                            <input wire:model.defer="rule_base_price_override" type="number" step="0.01" min="0">
                        </div>
                        <div>
                            <label class="cu-field-label">Multiplicateur</label>
                            <input wire:model.defer="rule_price_multiplier" type="number" step="0.01" min="0.1">
                        </div>
                        <div>
                            <label class="cu-field-label">Délai minimum (h)</label>
                            <input wire:model.defer="rule_minimum_notice_hours" type="number" min="0">
                        </div>
                        <div>
                            <label class="cu-field-label">Capacité max / jour</label>
                            <input wire:model.defer="rule_maximum_daily_capacity" type="number" min="1">
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <label class="cu-choice-card !items-center"><input wire:model.defer="rule_enabled" type="checkbox"> <span>Service actif sur la zone</span></label>
                        <label class="cu-choice-card !items-center"><input wire:model.defer="rule_requires_manual_validation" type="checkbox"> <span>Validation manuelle sur la zone</span></label>
                    </div>

                    <div class="mt-5 flex gap-3">
                        <button wire:click="saveZoneRule" class="cu-btn-primary">Enregistrer la règle</button>
                        <button wire:click="resetRuleForm" class="cu-btn-secondary">Réinitialiser</button>
                    </div>

                    <div class="mt-6">
                        <x-table-shell title="Règles actives" subtitle="Vue consolidée des règles déjà créées pour ce service.">
                            <table class="min-w-full cu-table">
                                <thead>
                                    <tr>
                                        <th>Zone</th>
                                        <th>Statut</th>
                                        <th>Prix</th>
                                        <th>Mult.</th>
                                        <th>Délai</th>
                                        <th>Capacité</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($selectedService->zoneServiceRules->sortBy(fn($rule) => $rule->serviceZone->name ?? '') as $rule)
                                        <tr>
                                            <td class="font-medium text-slate-900">{{ $rule->serviceZone->name ?? 'Zone supprimée' }}</td>
                                            <td>
                                                @if($rule->is_enabled)
                                                    <span class="cu-chip !border-emerald-200 !bg-emerald-50 !text-emerald-700">Actif</span>
                                                @else
                                                    <span class="cu-chip !border-red-200 !bg-red-50 !text-red-700">Bloqué</span>
                                                @endif
                                                @if($rule->requires_manual_validation)
                                                    <span class="cu-chip !ml-1 !border-amber-200 !bg-amber-50 !text-amber-700">Manuel</span>
                                                @endif
                                            </td>
                                            <td>{{ $rule->base_price_override ? '€ '.number_format((float) $rule->base_price_override, 2, ',', ' ') : '—' }}</td>
                                            <td>{{ number_format((float) $rule->price_multiplier, 2, ',', ' ') }}</td>
                                            <td>{{ $rule->minimum_notice_hours ?? '—' }}</td>
                                            <td>{{ $rule->maximum_daily_capacity ?? '—' }}</td>
                                            <td class="text-right">
                                                <button wire:click="editZoneRule({{ $rule->service_zone_id }})" class="cu-btn-secondary">Éditer</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <x-empty-state title="Aucune règle de zone" message="Sélectionnez une zone et enregistrez la première règle pour ce service." icon="🗺️" />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-table-shell>
                    </div>
                </x-app-card>
            @endif
        </div>
    </div>
</div>
