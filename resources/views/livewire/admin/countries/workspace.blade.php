        <div class="xl:col-span-2 space-y-6">
            @if($selectedCountry)
                <x-app-card title="Paramètres du pays" subtitle="Locale, devise, indicatif et activation du marché.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code ISO 2</label>
                            <input type="text" wire:model.defer="iso_code" maxlength="2" class="w-full border-gray-300 rounded-lg shadow-sm uppercase">
                            @error('iso_code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code ISO 3</label>
                            <input type="text" wire:model.defer="iso3_code" maxlength="3" class="w-full border-gray-300 rounded-lg shadow-sm uppercase">
                            @error('iso3_code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom public</label>
                            <input type="text" wire:model.defer="name" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom officiel</label>
                            <input type="text" wire:model.defer="official_name" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('official_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Locale par défaut</label>
                            <input type="text" wire:model.defer="default_locale" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('default_locale') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Devise</label>
                            <input type="text" wire:model.defer="currency_code" maxlength="3" class="w-full border-gray-300 rounded-lg shadow-sm uppercase">
                            @error('currency_code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Indicatif</label>
                            <input type="text" wire:model.defer="phone_code" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('phone_code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fuseau horaire</label>
                            <input type="text" wire:model.defer="timezone" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('timezone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-4 pt-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model.defer="is_active" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                Marché actif
                            </label>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="toggleCountryStatus({{ $selectedCountry->id }})" class="cu-btn-secondary">
                                    {{ $selectedCountry->is_active ? 'Désactiver le pays' : 'Activer le pays' }}
                                </button>
                                <button type="button" wire:click="saveCountry" class="cu-btn-primary">
                                    Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </x-app-card>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                    <x-kpi-card title="Régions" :value="$selectedCountry->regions_count" tone="slate" icon="🗺️" />
                    <x-kpi-card title="Provinces" :value="$selectedCountry->provinces_count" tone="blue" icon="📍" />
                    <x-kpi-card title="Communes" :value="$selectedCountry->communes_count" tone="amber" icon="🏙️" />
                    <x-kpi-card title="Codes postaux" :value="$selectedCountry->postal_codes_count" tone="green" icon="📮" />
                    <x-kpi-card title="Zones de service" :value="$selectedCountry->service_zones_count" tone="slate" icon="🧭" />
                </div>

                <x-app-card title="Lecture opérationnelle" subtitle="Ce pays est prêt quand la géographie, les zones et les règles de service sont branchées.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-slate-600">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="font-semibold text-slate-900">Structure géographique</p>
                            <p class="mt-2">{{ $selectedCountry->regions_count }} région(s), {{ $selectedCountry->provinces_count }} province(s) et {{ $selectedCountry->communes_count }} commune(s).</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="font-semibold text-slate-900">Couverture réservable</p>
                            <p class="mt-2">{{ $selectedCountry->postal_codes_count }} code(s) postal(aux) et {{ $selectedCountry->service_zones_count }} zone(s) de service rattachées.</p>
                        </div>
                    </div>
                </x-app-card>
            @else
                <div class="cu-card p-8 text-center text-slate-500">
                    Sélectionne un pays dans la colonne de gauche pour afficher ses paramètres.
                </div>
            @endif
        </div>
