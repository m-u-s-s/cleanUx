        <div class="xl:col-span-1 space-y-4">
            <x-filter-panel title="Marchés" subtitle="Recherche, stage et sélection du pays actif.">
                <div class="space-y-3">
                    <input type="text" wire:model.live="search" placeholder="Nom, ISO, devise..." class="w-full rounded-lg border-gray-300 shadow-sm">
                    <select wire:model.live="stageFilter" class="w-full rounded-lg border-gray-300 shadow-sm">
                        <option value="">— Tous les stages —</option>
                        <option value="draft">Brouillon</option>
                        <option value="catalog_only">Catalogue uniquement</option>
                        <option value="booking_enabled">Réservation active</option>
                        <option value="mission_enabled">Mission active</option>
                        <option value="billing_enabled">Facturation active</option>
                        <option value="ready_for_launch">Prêt au lancement</option>
                    </select>
                </div>
            </x-filter-panel>

            <x-app-card title="Pays" subtitle="Choisis un marché pour configurer son exploitation.">
                <div class="space-y-3">
                    @forelse($countries as $country)
                        @php($stage = $country->operationalSetting?->launch_stage_label ?? 'Brouillon')
                        @php($score = $country->launchReadiness?->readiness_score ?? 0)
                        <button type="button" wire:click="selectCountry({{ $country->id }})" class="w-full rounded-2xl border p-4 text-left transition {{ $selectedCountry && $selectedCountry->id === $country->id ? 'border-blue-500 bg-blue-50 shadow' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $country->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $country->iso_code }} • {{ $country->currency_code }} • {{ $country->default_locale }}</div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $country->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $country->is_active ? 'Actif' : 'Inactif' }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-600">
                                <span>{{ $stage }}</span>
                                <span>Readiness {{ $score }}%</span>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center text-slate-500">Aucun pays disponible.</div>
                    @endforelse
                </div>
            </x-app-card>
        </div>
