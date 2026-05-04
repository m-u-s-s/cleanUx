<div class="space-y-6" data-phase2u-root="true">
    @include('livewire.admin.governance.command-hints')

    @includeIf('livewire.admin.readiness.layout-stack')

<div class="space-y-6" data-phase2s-root="true">
    @includeIf('livewire.admin.pilotage.layout-stack')

<div class="space-y-6">
    <x-page-shell
        title="Centre de contrôle des modules"
        subtitle="Active, restreins ou verrouille les modules par rôle, plan, organisation ou zone sans toucher au code métier."
        eyebrow="Pilotage plateforme"
    >
        <x-slot:actions>
            <span class="cu-inline-stat">{{ $modules->count() }} module(s) visibles</span>
            @if($editingModuleId)
                <span class="cu-inline-stat">Configuration ouverte</span>
            @endif
        </x-slot:actions>
    </x-page-shell>

    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-4">
        <x-kpi-card title="Modules visibles" :value="$modules->count()" tone="slate" icon="🧩" />
        <x-kpi-card title="Actifs" :value="$modules->where('is_enabled', true)->count()" tone="green" icon="✅" />
        <x-kpi-card title="Verrouillés" :value="$modules->where('is_locked', true)->count()" tone="amber" icon="🔒" />
        <x-kpi-card title="Catégories" :value="count($categories)" tone="blue" icon="🗂️" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-filter-panel class="xl:col-span-1" title="Recherche et filtres" subtitle="Affiche rapidement les modules pertinents.">
            <div class="space-y-4">
                <div class="cu-filter-grid">
                    <div>
                        <label class="text-sm text-slate-600">Rechercher</label>
                        <input type="text" wire:model.live.debounce.300ms="search" class="mt-1" placeholder="Nom, clé, description...">
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Catégorie</label>
                        <select wire:model.live="category" class="mt-1">
                            <option value="">— Toutes —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Stratégie</label>
                        <select wire:model.live="strategy" class="mt-1">
                            <option value="">— Toutes —</option>
                            @foreach ($strategyOptions as $option)
                                <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Statut</label>
                        <select wire:model.live="status" class="mt-1">
                            <option value="">— Tous —</option>
                            <option value="enabled">Actifs</option>
                            <option value="disabled">Désactivés</option>
                            <option value="locked">Verrouillés</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse ($modules as $module)
                        <button type="button" wire:click="editModule({{ $module->id }})"
                            class="w-full rounded-2xl border p-4 text-left transition {{ $editingModuleId === $module->id ? 'border-sky-300 bg-sky-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $module->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $module->key }}</p>
                                    <p class="mt-2 text-sm text-slate-500">{{ $module->description }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $module->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $module->is_enabled ? 'Actif' : 'Off' }}</span>
                                    @if ($module->is_locked)
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Verrouillé</span>
                                    @endif
                                </div>
                            </div>
                        </button>
                    @empty
                        <x-empty-state title="Aucun module trouvé" message="Adapte tes filtres pour retrouver un module plateforme." icon="⚙️" />
                    @endforelse
                </div>
            </div>
        </x-filter-panel>

        <x-app-card class="xl:col-span-2" title="Configuration du module" subtitle="Applique des règles d’activation fines sans casser le rollout existant.">
            @if ($editingModuleId)
                <div class="space-y-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">{{ $name }}</h3>
                            <p class="text-sm text-slate-500">Configuration détaillée du module sélectionné.</p>
                        </div>
                        <button type="button" wire:click="toggleEnabled({{ $editingModuleId }})" class="cu-btn-secondary">{{ $is_enabled ? 'Désactiver' : 'Activer' }}</button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm text-slate-600">Nom</label>
                            <input type="text" wire:model="name" class="mt-1">
                            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Catégorie</label>
                            <input type="text" wire:model="category_value" class="mt-1">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-slate-600">Description</label>
                            <textarea wire:model="description" rows="3" class="mt-1"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm text-slate-600">Stratégie de rollout</label>
                            <select wire:model="rollout_strategy" class="mt-1">
                                @foreach ($strategyOptions as $option)
                                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <input type="checkbox" wire:model="is_enabled" class="rounded border-slate-300">
                            <span>Module activé</span>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <input type="checkbox" wire:model="is_locked" class="rounded border-slate-300">
                            <span>Module verrouillé</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <x-app-card muted title="Audience autorisée" subtitle="Définis les rôles, plans, zones et organisations ouvertes.">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-700">Rôles autorisés</label>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        @foreach ($roleOptions as $role)
                                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                                <input type="checkbox" wire:model="allowed_roles" value="{{ $role }}" class="rounded border-slate-300">
                                                <span>{{ ucfirst($role) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-700">Plans autorisés</label>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        @foreach ($planOptions as $plan)
                                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                                <input type="checkbox" wire:model="allowed_plans" value="{{ $plan }}" class="rounded border-slate-300">
                                                <span>{{ ucfirst($plan) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-700">Zones autorisées</label>
                                    <div class="mt-2 grid gap-2 max-h-48 overflow-y-auto">
                                        @foreach ($zones as $zone)
                                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                                <input type="checkbox" wire:model="allowed_zone_ids" value="{{ $zone->id }}" class="rounded border-slate-300">
                                                <span>{{ $zone->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-app-card>

                        <x-app-card muted title="Restrictions et organisations" subtitle="Définis les exclusions et le ciblage organisationnel.">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-700">Rôles refusés</label>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        @foreach ($roleOptions as $role)
                                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                                <input type="checkbox" wire:model="denied_roles" value="{{ $role }}" class="rounded border-slate-300">
                                                <span>{{ ucfirst($role) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-700">Plans refusés</label>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        @foreach ($planOptions as $plan)
                                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                                <input type="checkbox" wire:model="denied_plans" value="{{ $plan }}" class="rounded border-slate-300">
                                                <span>{{ ucfirst($plan) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                    <input type="checkbox" wire:model="allow_all_organizations" class="rounded border-slate-300">
                                    <span>Autoriser toutes les organisations</span>
                                </label>
                                <div>
                                    <label class="text-sm font-semibold text-slate-700">Organisations autorisées</label>
                                    <div class="mt-2 grid gap-2 max-h-48 overflow-y-auto">
                                        @foreach ($organizations as $organization)
                                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                                <input type="checkbox" wire:model="allowed_organization_ids" value="{{ $organization->id }}" class="rounded border-slate-300">
                                                <span>{{ $organization->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-app-card>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" wire:click="save" class="cu-btn-primary">Enregistrer les règles</button>
                        <button type="button" wire:click="editModule({{ $editingModuleId }})" class="cu-btn-secondary">Réinitialiser</button>
                    </div>
                </div>
            @else
                <x-empty-state title="Choisis un module" message="Sélectionne un module dans la colonne de gauche pour ouvrir sa configuration détaillée." icon="🧠" />
            @endif
        </x-app-card>
    </div>
</div>

</div>
</div>