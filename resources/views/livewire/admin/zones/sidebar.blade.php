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
            @auth
            @if (auth()->user()->providerProfile && auth()->user()->providerProfile->verification_status !== 'verified')
            <a href="{{ route('provider.onboarding') }}"
                class="rounded-2xl border-2 border-amber-300 bg-amber-50 px-4 py-3 text-sm">
                <div class="font-semibold text-amber-900">📋 Mon inscription</div>
                <div class="text-xs text-amber-700 mt-0.5">
                    Étape {{ auth()->user()->providerProfile->onboarding_step }} / 6
                </div>
            </a>
            @endif
            @endauth
        </div>