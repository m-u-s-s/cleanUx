        <div class="xl:col-span-1 space-y-4">
            <x-filter-panel title="Filtres" subtitle="Recherche rapide et statut du marché.">
                <div class="grid grid-cols-1 gap-3">
                    <input type="text" wire:model.live="search" placeholder="Nom, ISO, devise, locale..."
                        class="w-full border-gray-300 rounded-lg shadow-sm">

                    <select wire:model.live="statusFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Tous les statuts —</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Inactifs</option>
                    </select>
                </div>
            </x-filter-panel>

            <x-app-card title="Liste des pays" subtitle="Sélectionner un marché pour gérer ses paramètres locaux.">
                <div class="space-y-3">
                    @forelse($countries as $country)
                        <button type="button" wire:click="selectCountry({{ $country->id }})"
                            class="w-full text-left bg-white rounded-2xl border shadow-sm p-4 transition {{ $selectedCountry && $selectedCountry->id === $country->id ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200 hover:border-slate-300' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $country->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $country->iso_code }} @if($country->iso3_code)· {{ $country->iso3_code }}@endif · {{ $country->currency_code }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ $country->default_locale }} · {{ $country->timezone }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-slate-600">
                                        <span class="rounded-full bg-slate-100 px-2 py-1">{{ $country->regions_count }} régions</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1">{{ $country->postal_codes_count }} CP</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1">{{ $country->service_zones_count }} zones</span>
                                    </div>
                                </div>
                                <div class="text-right space-y-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold {{ $statusPill((bool) $country->is_active) }}">
                                        {{ $country->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                    <div>
                                        <button type="button" wire:click.stop="toggleCountryStatus({{ $country->id }})" class="cu-btn-secondary text-xs">
                                            {{ $country->is_active ? 'Désactiver' : 'Activer' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="bg-white border rounded-2xl p-6 text-center text-gray-500 italic">
                            Aucun pays trouvé.
                        </div>
                    @endforelse
                </div>
            </x-app-card>
        </div>
