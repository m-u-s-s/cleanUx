<div class="space-y-6">
    <x-page-shell eyebrow="Territoire" title="Gestion des zones" subtitle="Pilotage Belgique par zones, règles de service et affectations opérationnelles." />

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @php
        $statusPill = fn ($status) => match ($status) {
            'active' => 'bg-emerald-100 text-emerald-700',
            'paused' => 'bg-amber-100 text-amber-700',
            'archived' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
        <x-kpi-card title="Total zones" :value="$zoneStats['total']" tone="slate" icon="🧭" />
        <x-kpi-card title="Actives" :value="$zoneStats['active']" tone="green" icon="✅" />
        <x-kpi-card title="En pause" :value="$zoneStats['paused']" tone="amber" icon="⏸️" />
        <x-kpi-card title="Réservables" :value="$zoneStats['bookable']" tone="blue" icon="📅" />
        <x-kpi-card title="Visibles" :value="$zoneStats['visible']" tone="slate" icon="👁️" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1 space-y-4">
            <x-filter-panel title="Filtres" subtitle="Recherche, statut, couverture, visibilité et territoire.">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-1 gap-3">
                    <input type="text" wire:model.live="search" placeholder="Nom, code, slug..."
                        class="w-full border-gray-300 rounded-lg shadow-sm">

                    <select wire:model.live="statusFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Tous les statuts —</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="archived">Archivée</option>
                    </select>

                    <select wire:model.live="coverageFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Tous types —</option>
                        <option value="national">National</option>
                        <option value="region">Région</option>
                        <option value="province">Province</option>
                        <option value="commune">Commune</option>
                        <option value="postal_code">Code postal</option>
                        <option value="custom">Custom</option>
                    </select>

                    <select wire:model.live="bookableFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Réservable ou non —</option>
                        <option value="1">Réservable</option>
                        <option value="0">Non réservable</option>
                    </select>

                    <select wire:model.live="visibilityFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Visible ou non —</option>
                        <option value="1">Visible</option>
                        <option value="0">Masquée</option>
                    </select>

                    <select wire:model.live="regionFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Toutes les régions —</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="provinceFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Toutes les provinces —</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="button" wire:click="resetFilters" class="cu-btn-secondary">
                        Réinitialiser les filtres
                    </button>
                </div>
            </x-filter-panel>

            <x-app-card title="Liste des zones" subtitle="Sélection d’une zone pour gérer ses règles et affectations.">
                <div class="space-y-3">
                @forelse($zones as $zone)
                    <button type="button" wire:click="selectZone({{ $zone->id }})"
                        class="w-full text-left bg-white rounded-2xl border shadow-sm p-4 transition {{ $selectedZone && $selectedZone->id === $zone->id ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200 hover:border-slate-300' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $zone->name }}</p>
                                <p class="text-xs text-slate-500">{{ $zone->code }} · {{ $zone->coverage_type }}</p>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ $zone->region?->name ?? '—' }}
                                    @if($zone->province)
                                        · {{ $zone->province->name }}
                                    @endif
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-slate-600">
                                    <span class="rounded-full bg-slate-100 px-2 py-1">{{ $zone->postal_codes_count }} CP</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1">{{ $zone->enabled_service_rules_count }} services actifs</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1">{{ $zone->active_employee_assignments_count }} employés actifs</span>
                                </div>
                            </div>
                            <div class="text-right space-y-1">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold {{ $statusPill($zone->status) }}">
                                    {{ ucfirst($zone->status) }}
                                </span>
                                <div class="text-[11px] text-slate-500">{{ $zone->is_bookable ? 'Réservable' : 'Non réservable' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $zone->is_visible ? 'Visible' : 'Masquée' }}</div>
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="bg-white border rounded-2xl p-6 text-center text-gray-500 italic">
                        Aucune zone trouvée.
                    </div>
                @endforelse
            </div>

                <div>{{ $zones->links() }}</div>
            </x-app-card>
        </div>

        <div class="xl:col-span-2 space-y-6">
            @if($selectedZone)
                <div class="bg-white rounded-2xl shadow border p-5 space-y-5">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">{{ $selectedZone->name }}</h3>
                            <p class="text-sm text-slate-500">{{ $selectedZone->code }} · {{ $selectedZone->slug }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700">{{ $selectedZone->coverage_type }}</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full {{ $selectedZone->is_visible ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }}">{{ $selectedZone->is_visible ? 'Visible' : 'Masquée' }}</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full {{ $selectedZone->is_bookable ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $selectedZone->is_bookable ? 'Bookable' : 'Non bookable' }}</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full {{ $statusPill($selectedZone->status) }}">{{ ucfirst($selectedZone->status) }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="toggleZoneBookability"
                                class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                {{ $selectedZone->is_bookable ? 'Rendre non bookable' : 'Rendre bookable' }}
                            </button>
                            <button type="button" wire:click="toggleZoneVisibility"
                                class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition">
                                {{ $selectedZone->is_visible ? 'Masquer' : 'Rendre visible' }}
                            </button>
                            @if($selectedZone->status !== 'active')
                                <button type="button" wire:click="setZoneStatus('active')"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                                    Activer
                                </button>
                            @endif
                            @if($selectedZone->status !== 'paused')
                                <button type="button" wire:click="setZoneStatus('paused')"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition">
                                    Mettre en pause
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Codes postaux</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $selectedZone->postal_codes_count }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Services actifs</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $selectedZone->enabled_service_rules_count }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Validation manuelle</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $selectedZone->manual_validation_rules_count }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Employés actifs</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $selectedZone->active_employee_assignments_count }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Sites entreprise</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $selectedZone->organization_sites_count }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                            <input type="text" wire:model="name" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input type="text" value="{{ $code }}" disabled class="w-full border-gray-200 bg-slate-50 rounded-lg shadow-sm text-slate-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                            <select wire:model="status" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="archived">Archivée</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type de couverture</label>
                            <select wire:model="coverage_type" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="national">National</option>
                                <option value="region">Région</option>
                                <option value="province">Province</option>
                                <option value="commune">Commune</option>
                                <option value="postal_code">Code postal</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priorité</label>
                            <input type="number" min="1" wire:model="priority" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Délai minimum (h)</label>
                            <input type="number" min="0" wire:model="minimum_notice_hours" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Capacité max / jour</label>
                            <input type="number" min="1" wire:model="maximum_daily_jobs" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buffer (minutes)</label>
                            <input type="number" min="0" wire:model="time_buffer_minutes" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Surcharge déplacement (€)</label>
                            <input type="number" min="0" step="0.01" wire:model="travel_surcharge" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div class="md:col-span-2 flex flex-wrap gap-6 pt-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="is_visible" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                Visible
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="is_bookable" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                Réservable
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
                            <textarea rows="3" wire:model="notes" class="w-full border-gray-300 rounded-lg shadow-sm"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 pt-2">
                        <div class="rounded-2xl border bg-slate-50 p-4">
                            <h4 class="font-semibold text-slate-900">🌍 Couverture</h4>
                            <div class="mt-3 space-y-2 text-sm text-slate-600">
                                <p><span class="font-medium text-slate-800">Pays :</span> {{ $selectedZone->country?->name ?? '—' }}</p>
                                <p><span class="font-medium text-slate-800">Région :</span> {{ $selectedZone->region?->name ?? '—' }}</p>
                                <p><span class="font-medium text-slate-800">Province :</span> {{ $selectedZone->province?->name ?? '—' }}</p>
                                <p><span class="font-medium text-slate-800">Commune :</span> {{ $selectedZone->commune?->name ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border bg-slate-50 p-4">
                            <h4 class="font-semibold text-slate-900">📮 Codes postaux liés</h4>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse($selectedZone->postalCodes->take(12) as $postalCode)
                                    <span class="inline-flex items-center rounded-full bg-white border px-3 py-1 text-xs text-slate-700">
                                        {{ $postalCode->code }} {{ $postalCode->city_name }}
                                    </span>
                                @empty
                                    <span class="text-sm text-slate-500 italic">Aucun code postal lié.</span>
                                @endforelse
                                @if($selectedZone->postalCodes->count() > 12)
                                    <span class="inline-flex items-center rounded-full bg-white border px-3 py-1 text-xs text-slate-700">
                                        +{{ $selectedZone->postalCodes->count() - 12 }} autres
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" wire:click="saveZone"
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700 transition">
                            Enregistrer la zone
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">🧩 Services liés</h3>
                                <p class="text-sm text-slate-500">Active, ajuste le prix, la validation manuelle et la capacité par service.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <select wire:model="copyRulesFromZoneId" class="border-gray-300 rounded-lg shadow-sm text-sm">
                                    <option value="">Copier depuis une zone...</option>
                                    @foreach($sourceZones as $zoneOption)
                                        <option value="{{ $zoneOption->id }}">{{ $zoneOption->name }} · {{ $zoneOption->code }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="copyServiceRulesFromZone"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition">
                                    Copier règles
                                </button>
                                <button type="button" wire:click="saveAllServiceRules"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                    Tout enregistrer
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4 max-h-[760px] overflow-y-auto pr-1">
                            @foreach($serviceRules as $serviceId => $rule)
                                <div class="border rounded-2xl p-4 bg-slate-50">
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $rule['service_name'] }}</p>
                                            <p class="text-xs text-slate-500">{{ $rule['service_type'] ?: 'Type non défini' }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2 justify-end">
                                            @if($rule['requires_manual_validation'])
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">Validation manuelle</span>
                                            @endif
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" wire:model="serviceRules.{{ $serviceId }}.is_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                                Actif
                                            </label>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Prix spécifique (€)</label>
                                            <input type="number" step="0.01" min="0" wire:model="serviceRules.{{ $serviceId }}.base_price_override" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Multiplicateur prix</label>
                                            <input type="number" step="0.01" min="0.1" wire:model="serviceRules.{{ $serviceId }}.price_multiplier" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Délai minimum (h)</label>
                                            <input type="number" min="0" wire:model="serviceRules.{{ $serviceId }}.minimum_notice_hours" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Capacité / jour</label>
                                            <input type="number" min="1" wire:model="serviceRules.{{ $serviceId }}.maximum_daily_capacity" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" wire:model="serviceRules.{{ $serviceId }}.requires_manual_validation" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                                Validation manuelle requise
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex justify-end">
                                        <button type="button" wire:click="saveServiceRule({{ $serviceId }})"
                                            class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                            Enregistrer le service
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">👷 Employés liés</h3>
                                <p class="text-sm text-slate-500">Assigne, priorise et maintiens les rôles primaire, secondaire ou backup.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <select wire:model="employeeToAssign" class="w-full border-gray-300 rounded-lg shadow-sm">
                                    <option value="">— Choisir un employé —</option>
                                    @foreach($availableEmployees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>

                                <select wire:model="assignmentType" class="w-full border-gray-300 rounded-lg shadow-sm">
                                    <option value="primary">Primary</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="backup">Backup</option>
                                </select>

                                <input type="number" min="1" wire:model="assignmentPriority" placeholder="Priorité"
                                    class="w-full border-gray-300 rounded-lg shadow-sm">

                                <input type="text" wire:model="assignmentNotes" placeholder="Notes internes"
                                    class="w-full border-gray-300 rounded-lg shadow-sm">
                            </div>

                            <div class="flex justify-end">
                                <button type="button" wire:click="assignEmployee"
                                    class="inline-flex items-center px-4 py-2 rounded-xl bg-emerald-600 text-white font-medium hover:bg-emerald-700 transition">
                                    Affecter l’employé
                                </button>
                            </div>

                            <div class="space-y-3">
                                @forelse($selectedZone->employeeAssignments->sortBy('coverage_priority') as $assignment)
                                    <div class="border rounded-2xl p-4 bg-slate-50 space-y-3">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ $assignment->user?->name ?? 'Employé supprimé' }}</p>
                                                <p class="text-sm text-slate-500">
                                                    Affectation actuelle : {{ ucfirst($assignment->assignment_type) }}
                                                </p>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $assignment->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                                    {{ $assignment->is_active ? 'Actif' : 'Inactif' }}
                                                </span>

                                                <button type="button" wire:click="toggleAssignment({{ $assignment->id }})"
                                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-white border text-slate-700 text-sm font-medium hover:bg-slate-100 transition">
                                                    {{ $assignment->is_active ? 'Désactiver' : 'Réactiver' }}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <select wire:model="assignmentEdits.{{ $assignment->id }}.assignment_type" class="w-full border-gray-300 rounded-lg shadow-sm">
                                                <option value="primary">Primary</option>
                                                <option value="secondary">Secondary</option>
                                                <option value="backup">Backup</option>
                                            </select>

                                            <input type="number" min="1" wire:model="assignmentEdits.{{ $assignment->id }}.coverage_priority" class="w-full border-gray-300 rounded-lg shadow-sm">

                                            <input type="text" wire:model="assignmentEdits.{{ $assignment->id }}.notes" placeholder="Notes internes" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div class="flex flex-wrap justify-end gap-2">
                                            <button type="button" wire:click="saveAssignment({{ $assignment->id }})"
                                                class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                                Enregistrer l’affectation
                                            </button>
                                            <button type="button" wire:click="removeAssignment({{ $assignment->id }})"
                                                class="inline-flex items-center px-3 py-2 rounded-xl bg-rose-100 text-rose-700 text-sm font-medium hover:bg-rose-200 transition">
                                                Supprimer
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="border rounded-xl p-4 text-sm text-slate-500 italic">
                                        Aucun employé affecté à cette zone.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">🕓 Historique des modifications</h3>
                                <p class="text-sm text-slate-500">Dernières actions faites sur cette zone.</p>
                            </div>

                            <div class="space-y-3">
                                @forelse($zoneHistory as $log)
                                    <div class="border rounded-2xl p-4 bg-slate-50">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="font-medium text-slate-900">{{ str_replace('_', ' ', $log->action) }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <p class="text-sm text-slate-500 mt-1">Par {{ $log->user?->name ?? 'Système' }}</p>
                                        @if(is_array($log->meta) && count($log->meta))
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach(collect($log->meta)->take(4) as $metaKey => $metaValue)
                                                    @if(is_scalar($metaValue) || $metaValue === null)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-white border text-[11px] text-slate-600">
                                                            {{ str_replace('_', ' ', (string) $metaKey) }} : {{ $metaValue === null ? '—' : $metaValue }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="border rounded-xl p-4 text-sm text-slate-500 italic">
                                        Aucun historique pour cette zone.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white rounded-2xl shadow border p-8 text-center text-slate-500">
                    Aucune zone sélectionnée.
                </div>
            @endif
        </div>
    </div>
</div>
