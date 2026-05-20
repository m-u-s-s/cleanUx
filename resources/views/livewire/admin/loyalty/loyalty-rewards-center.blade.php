<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Marketplace fidélité</p>
                <h1 class="text-2xl font-black text-slate-900">Récompenses & Rédemptions</h1>
                <p class="text-sm text-slate-500">Catalogue de récompenses échangeables contre des points + suivi rédemptions.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Récompenses</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['rewards_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Actives</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['rewards_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Rédemptions</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['redemptions_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En attente</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($stats['redemptions_pending']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Confirmées</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($stats['redemptions_confirmed']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Pts dépensés</p>
                <p class="text-2xl font-black text-rose-600">{{ number_format($stats['points_spent_total']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b">
            <button wire:click="setTab('rewards')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'rewards' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Catalogue
            </button>
            <button wire:click="setTab('redemptions')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'redemptions' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Rédemptions
            </button>
        </div>

        @if ($tab === 'rewards')
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 flex flex-col md:flex-row md:items-center gap-3 border-b">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche nom / code / catégorie..." class="rounded-lg border-slate-300 text-sm flex-1">
                    <select wire:model.live="rewardTypeFilter" class="rounded-lg border-slate-300 text-sm">
                        <option value="">Tous types</option>
                        <option value="discount_code">Code réduction</option>
                        <option value="service_credit">Crédit service</option>
                        <option value="physical_item">Objet physique</option>
                        <option value="partner_voucher">Voucher partenaire</option>
                        <option value="charity_donation">Don caritatif</option>
                    </select>
                    <button wire:click="openCreate" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">+ Nouvelle récompense</button>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Nom</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Points</th>
                            <th class="px-3 py-2">Valeur</th>
                            <th class="px-3 py-2">Tier min</th>
                            <th class="px-3 py-2">Stock</th>
                            <th class="px-3 py-2">État</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rewards as $r)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-xs">{{ $r->code }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $r->name }}</td>
                                <td class="px-3 py-2 text-xs">{{ $r->reward_type }}</td>
                                <td class="px-3 py-2 font-bold text-indigo-700">{{ number_format($r->points_cost) }}</td>
                                <td class="px-3 py-2">{{ $r->valueFormatted() }}</td>
                                <td class="px-3 py-2">{{ ['Bronze','Silver','Gold','Platinum'][$r->min_tier_level] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $r->stock_remaining === null ? '∞' : $r->stock_remaining }}</td>
                                <td class="px-3 py-2">
                                    @if ($r->is_active)
                                        <span class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold">Active</span>
                                    @else
                                        <span class="inline-block rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-semibold">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="openEdit({{ $r->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Éditer</button>
                                    <button wire:click="toggleActive({{ $r->id }})" class="text-slate-600 hover:underline text-xs font-semibold ml-2">Toggle</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-6 text-center text-slate-400">Aucune récompense.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $rewards->links() }}</div>
            </div>
        @endif

        @if ($tab === 'redemptions')
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 flex items-center gap-3 border-b">
                    <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm">
                        <option value="">Tous statuts</option>
                        <option value="pending">En attente</option>
                        <option value="confirmed">Confirmées</option>
                        <option value="delivered">Livrées</option>
                        <option value="cancelled">Annulées</option>
                    </select>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Utilisateur</th>
                            <th class="px-3 py-2">Récompense</th>
                            <th class="px-3 py-2">Points</th>
                            <th class="px-3 py-2">Voucher</th>
                            <th class="px-3 py-2">Statut</th>
                            <th class="px-3 py-2">Créée</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($redemptions as $d)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-xs">{{ $d->code }}</td>
                                <td class="px-3 py-2">{{ $d->user?->name }} <span class="text-slate-400 text-xs">{{ $d->user?->email }}</span></td>
                                <td class="px-3 py-2">{{ $d->reward?->name }}</td>
                                <td class="px-3 py-2 font-bold text-indigo-700">{{ number_format($d->points_spent) }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $d->voucher_code ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @php $color = ['pending'=>'amber','confirmed'=>'indigo','delivered'=>'emerald','cancelled'=>'rose','refunded'=>'slate'][$d->status] ?? 'slate'; @endphp
                                    <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $d->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $d->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if (in_array($d->status, ['pending', 'confirmed']))
                                        <button wire:click="markDelivered({{ $d->id }})" class="text-emerald-600 hover:underline text-xs font-semibold">Livrée</button>
                                        <button wire:click="cancelRedemption({{ $d->id }})" wire:confirm="Annuler et refunder les points?" class="text-rose-600 hover:underline text-xs font-semibold ml-2">Annuler</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-center text-slate-400">Aucune rédemption.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $redemptions->links() }}</div>
            </div>
        @endif

        @if ($showForm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40">
                <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-slate-900">{{ $editRewardId ? 'Éditer' : 'Nouvelle' }} récompense</h2>
                        <button wire:click="closeForm" class="text-slate-400 hover:text-slate-700">×</button>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-slate-600">Code interne</label>
                            <input wire:model="form_code" class="w-full rounded-lg border-slate-300 text-sm" type="text">
                            @error('form_code') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Nom affiché</label>
                            <input wire:model="form_name" class="w-full rounded-lg border-slate-300 text-sm" type="text">
                            @error('form_name') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-slate-600">Description</label>
                            <textarea wire:model="form_description" class="w-full rounded-lg border-slate-300 text-sm" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Type</label>
                            <select wire:model="form_reward_type" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="discount_code">Code réduction</option>
                                <option value="service_credit">Crédit service</option>
                                <option value="physical_item">Objet physique</option>
                                <option value="partner_voucher">Voucher partenaire</option>
                                <option value="charity_donation">Don caritatif</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Catégorie (optionnel)</label>
                            <input wire:model="form_category" class="w-full rounded-lg border-slate-300 text-sm" type="text">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Coût (points)</label>
                            <input wire:model="form_points_cost" class="w-full rounded-lg border-slate-300 text-sm" type="number" min="1">
                            @error('form_points_cost') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Valeur (centimes)</label>
                            <input wire:model="form_value_cents" class="w-full rounded-lg border-slate-300 text-sm" type="number" min="0">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Devise</label>
                            <select wire:model="form_currency" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Tier minimum</label>
                            <select wire:model="form_min_tier_level" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="0">Bronze (tous)</option>
                                <option value="1">Silver</option>
                                <option value="2">Gold</option>
                                <option value="3">Platinum</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Stock initial (vide = illimité)</label>
                            <input wire:model="form_stock_initial" class="w-full rounded-lg border-slate-300 text-sm" type="number" min="0">
                        </div>
                        <div class="col-span-2 flex items-center gap-2">
                            <input wire:model="form_is_active" type="checkbox" class="rounded border-slate-300">
                            <label class="text-sm text-slate-700">Active</label>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button wire:click="closeForm" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700">Annuler</button>
                        <button wire:click="save" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">Enregistrer</button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
