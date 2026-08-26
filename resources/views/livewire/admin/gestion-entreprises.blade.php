<div class="space-y-8">
    <x-page-shell eyebrow="Entreprise" title="Comptes entreprise" subtitle="Gère les comptes corporate, les sites, les contacts et les règles contractuelles de base.">
        <x-slot name="actions">
            <button wire:click="resetAccountForm" class="brio-btn-primary">Nouveau compte</button>
        </x-slot>
    </x-page-shell>

    @if (session()->has('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-4 space-y-6">
            <x-filter-panel title="Filtres entreprise" subtitle="Recherche, statut, type et zone.">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-1 gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche entreprise, TVA, email..." class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">

                    <select wire:model.live="status" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tous statuts</option>
                        <option value="active">Actif</option>
                        <option value="inactive">Inactif</option>
                        <option value="prospect">Prospect</option>
                        <option value="suspended">Suspendu</option>
                    </select>

                    <select wire:model.live="type" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tous types</option>
                        <option value="entreprise">Entreprise</option>
                        <option value="business">Business</option>
                        <option value="partner">Partner</option>
                        <option value="individual">Individual</option>
                    </select>

                    <select wire:model.live="zoneFilter" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Toutes zones</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
            </x-filter-panel>

            <x-app-card title="Liste entreprises" :subtitle="$accounts->total().' résultats'" padding="p-0 overflow-hidden">
                <div class="divide-y divide-slate-100">
                    @forelse($accounts as $account)
                        <button wire:click="selectAccount({{ $account->id }})" class="w-full text-left px-5 py-4 hover:bg-slate-50 transition {{ $selectedAccount?->id === $account->id ? 'bg-blue-50' : '' }}">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $account->name }}</p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ $account->legal_name ?: $account->slug }}
                                        @if($account->tva_number)
                                            · TVA {{ $account->tva_number }}
                                        @endif
                                    </p>
                                </div>
                                <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $account->status === 'active' ? 'bg-green-100 text-green-700' : ($account->status === 'prospect' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                    {{ ucfirst($account->status) }}
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                                <span class="px-2 py-1 rounded-full bg-slate-100">{{ ucfirst($account->type) }}</span>
                                <span class="px-2 py-1 rounded-full bg-slate-100">{{ $account->sites_count }} sites</span>
                                <span class="px-2 py-1 rounded-full bg-slate-100">{{ $account->users_count }} utilisateurs</span>
                                <span class="px-2 py-1 rounded-full bg-slate-100">{{ $account->rendez_vous_count }} RDV</span>
                                @if($account->is_key_account)
                                    <span class="px-2 py-1 rounded-full bg-purple-100 text-purple-700">Key account</span>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="px-5 py-8 text-sm text-slate-500">Aucun compte entreprise trouvé.</div>
                    @endforelse
                </div>

                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $accounts->links() }}
                </div>
            </x-app-card>
        </div>

        <div class="xl:col-span-4 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Fiche entreprise</h2>
                    @if($selectedAccount)
                        <span class="text-xs text-slate-500">#{{ $selectedAccount->id }}</span>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="name">Nom commercial</label>
                        <input id="name" wire:model.defer="name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="legal_name">Raison sociale</label>
                        <input id="legal_name" wire:model.defer="legal_name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="slug">Slug</label>
                        <input id="slug" wire:model.defer="slug" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('slug') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="tva_number">TVA</label>
                        <input id="tva_number" wire:model.defer="tva_number" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('tva_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="account_type">Type</label>
                        <select id="account_type" wire:model.defer="account_type" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="entreprise">Entreprise</option>
                            <option value="business">Business</option>
                            <option value="partner">Partner</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="account_status">Statut</label>
                        <select id="account_status" wire:model.defer="account_status" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                            <option value="prospect">Prospect</option>
                            <option value="suspended">Suspendu</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                        <input id="email" wire:model.defer="email" type="email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="phone">Téléphone</label>
                        <input id="phone" wire:model.defer="phone" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="billing_email">Email facturation</label>
                        <input id="billing_email" wire:model.defer="billing_email" type="email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="postal_code">Code postal</label>
                        <input id="postal_code" wire:model.defer="postal_code" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="address_line_1">Adresse</label>
                        <input id="address_line_1" wire:model.defer="address_line_1" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="address_line_2">Complément</label>
                        <input id="address_line_2" wire:model.defer="address_line_2" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="city">Ville</label>
                        <input id="city" wire:model.defer="city" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <h3 class="font-semibold text-slate-900 mb-3">Règles contractuelles</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="contract_reference">Référence contrat</label>
                            <input id="contract_reference" wire:model.defer="contract_reference" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="pricing_profile">Profil tarifaire</label>
                            <input id="pricing_profile" wire:model.defer="pricing_profile" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="standard, négocié, key-account...">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="sla_hours">SLA (heures)</label>
                            <input id="sla_hours" wire:model.defer="sla_hours" type="number" min="0" step="0.5" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="priority_zone_id">Zone prioritaire</label>
                            <select id="priority_zone_id" wire:model.defer="priority_zone_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Aucune</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="approval_mode">Mode d’approbation</label>
                            <select id="approval_mode" wire:model.defer="approval_mode" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="auto">Automatique</option>
                                <option value="manual">Approbation corporate</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="default_cost_center">Centre de coût par défaut</label>
                            <input id="default_cost_center" wire:model.defer="default_cost_center" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="negotiated_discount_percent">Remise négociée (%)</label>
                            <input id="negotiated_discount_percent" wire:model.defer="negotiated_discount_percent" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <label class="inline-flex items-center gap-2"><input wire:model.defer="is_multisite" type="checkbox" class="rounded border-slate-300 text-blue-600"> Multi-sites</label>
                    <label class="inline-flex items-center gap-2"><input wire:model.defer="is_key_account" type="checkbox" class="rounded border-slate-300 text-blue-600"> Key account</label>
                    <label class="inline-flex items-center gap-2"><input wire:model.defer="purchase_order_required" type="checkbox" class="rounded border-slate-300 text-blue-600"> PO obligatoire</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="notes">Notes</label>
                    <textarea id="notes" wire:model.defer="notes" rows="4" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <button wire:click="saveAccount" class="w-full inline-flex justify-center items-center px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                    Enregistrer le compte
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Utilisateurs liés</h2>
                    @if($selectedAccount)
                        <span class="text-xs text-slate-500">{{ $selectedAccount->users->count() }} liés</span>
                    @endif
                </div>

                @if($selectedAccount)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <select wire:model="user_to_attach" class="md:col-span-2 rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Sélectionner un utilisateur</option>
                            @foreach($availableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }} · {{ $user->role_label }}</option>
                            @endforeach
                        </select>
                        <select wire:model="user_role_mode" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="keep">Garder rôle</option>
                            <option value="entreprise">Passer en entreprise</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="user_site_scope_mode">Scope sites</label>
                            <select id="user_site_scope_mode" wire:model="user_site_scope_mode" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="all_sites">Tous les sites</option>
                                <option value="selected_sites">Sites sélectionnés</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="user_allowed_site_ids">Sites autorisés</label>
                            <select id="user_allowed_site_ids" wire:model="user_allowed_site_ids" multiple class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 min-h-[110px]">
                                @foreach(($selectedAccount?->sites ?? collect()) as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button wire:click="attachUser" class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition">
                        Rattacher l’utilisateur
                    </button>

                    <div class="space-y-3">
                        @forelse($selectedAccount->users as $user)
                            <div class="rounded-2xl border border-slate-200 p-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $user->email }} · {{ $user->role_label }}</p>
                                    <p class="text-xs text-slate-400">Scope sites : {{ data_get($user->metadata, "entreprise_site_scope.mode", "all_sites") === "selected_sites" ? "Sites sélectionnés" : "Tous les sites" }}</p>
                                </div>
                                <button wire:click="detachUser({{ $user->id }})" class="px-3 py-2 rounded-xl bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100">
                                    Détacher
                                </button>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aucun utilisateur rattaché.</div>
                        @endforelse
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Sélectionne une entreprise pour gérer ses utilisateurs.</div>
                @endif
            </div>
        </div>

        <div class="xl:col-span-4 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Sites entreprise</h2>
                    @if($selectedAccount)
                        <button wire:click="resetSiteForm" class="text-sm text-blue-600 hover:text-blue-800">Nouveau site</button>
                    @endif
                </div>

                @if($selectedAccount)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_name">Nom du site</label>
                            <input id="site_name" wire:model.defer="site_name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('site_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_code">Code site</label>
                            <input id="site_code" wire:model.defer="site_code" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('site_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_contact_name">Contact site</label>
                            <input id="site_contact_name" wire:model.defer="site_contact_name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_email">Email site</label>
                            <input id="site_email" wire:model.defer="site_email" type="email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_phone">Téléphone site</label>
                            <input id="site_phone" wire:model.defer="site_phone" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_postal_code">Code postal</label>
                            <input id="site_postal_code" wire:model.defer="site_postal_code" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_address_line_1">Adresse</label>
                            <input id="site_address_line_1" wire:model.defer="site_address_line_1" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_address_line_2">Complément</label>
                            <input id="site_address_line_2" wire:model.defer="site_address_line_2" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_city">Ville</label>
                            <input id="site_city" wire:model.defer="site_city" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_zone_id">Zone</label>
                            <select id="site_zone_id" wire:model.defer="site_zone_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Auto depuis code postal</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_approval_mode">Approbation site</label>
                            <select id="site_approval_mode" wire:model.defer="site_approval_mode" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="inherit">Hériter du compte</option>
                                <option value="auto">Auto</option>
                                <option value="manual">Approbation corporate</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="site_default_cost_center">Centre de coût site</label>
                            <input id="site_default_cost_center" wire:model.defer="site_default_cost_center" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="site_access_instructions">Consignes d’accès</label>
                        <textarea id="site_access_instructions" wire:model.defer="site_access_instructions" rows="3" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                        <label class="inline-flex items-center gap-2"><input wire:model.defer="site_is_primary" type="checkbox" class="rounded border-slate-300 text-blue-600"> Site principal</label>
                        <label class="inline-flex items-center gap-2"><input wire:model.defer="site_is_active" type="checkbox" class="rounded border-slate-300 text-blue-600"> Site actif</label>
                        <label class="inline-flex items-center gap-2"><input wire:model.defer="site_purchase_order_required" type="checkbox" class="rounded border-slate-300 text-blue-600"> PO obligatoire</label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button wire:click="saveSite" class="inline-flex justify-center items-center px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                            Enregistrer le site
                        </button>
                        <button wire:click="resetSiteForm" class="inline-flex justify-center items-center px-4 py-3 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition">
                            Réinitialiser
                        </button>
                    </div>

                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        @forelse($selectedAccount->sites->sortByDesc('is_primary') as $site)
                            <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $site->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $site->site_code ?: 'Sans code' }} · {{ $site->postal_code ?: '-' }} {{ $site->city ?: '' }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        @if($site->is_primary)
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">Principal</span>
                                        @endif
                                        @if($site->is_active)
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">Actif</span>
                                        @else
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">Inactif</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-xs text-slate-600 space-y-1">
                                    <p><span class="font-medium">Zone :</span> {{ $site->serviceZone?->name ?: 'Non résolue' }}</p>
                                    <p><span class="font-medium">Contact :</span> {{ $site->contact_name ?: '-' }} @if($site->email)· {{ $site->email }}@endif</p>
                                    <p><span class="font-medium">Approbation :</span> {{ data_get($site->metadata, "approval_mode", "inherit") }}</p>
                                    <p><span class="font-medium">Centre de coût :</span> {{ data_get($site->metadata, "default_cost_center", "—") }}</p>
                                </div>

                                <div class="flex gap-2">
                                    <button wire:click="editSite({{ $site->id }})" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">Modifier</button>
                                    <button wire:click="deleteSite({{ $site->id }})" wire:confirm="Supprimer ce site ?" class="px-3 py-2 rounded-xl bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100">Supprimer</button>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aucun site enregistré.</div>
                        @endforelse
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Sélectionne une entreprise pour gérer ses sites.</div>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Historique</h2>
                <div class="space-y-3">
                    @forelse($logs as $log)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-slate-900">{{ str_replace(['.', '_'], ' ', $log->action) }}</p>
                                <span class="text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Par {{ $log->user?->name ?: 'Système' }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aucun historique pour cette entreprise.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
