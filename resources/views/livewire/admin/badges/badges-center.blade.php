<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Gamification</p>
                <h1 class="text-2xl font-black text-slate-900">Provider Badges</h1>
                <p class="text-sm text-slate-500">Catalogue badges + awards historique.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Badges total</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['total_badges']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['active_badges']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total awards</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($stats['total_awards']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Awards 30j</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($stats['awards_last_30d']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b">
            <button wire:click="setTab('catalog')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'catalog' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Catalogue
            </button>
            <button wire:click="setTab('awards')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'awards' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Awards
            </button>
        </div>

        @if ($tab === 'catalog')
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 flex flex-col md:flex-row gap-3 border-b">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche nom / code..." class="rounded-lg border-slate-300 text-sm flex-1">
                    <button wire:click="openCreate" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">+ Nouveau badge</button>
                </div>

                <div class="brio-table-cadre"><table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Icon</th>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Nom</th>
                            <th class="px-3 py-2">Tier</th>
                            <th class="px-3 py-2">Critère</th>
                            <th class="px-3 py-2">Seuil</th>
                            <th class="px-3 py-2">État</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($badges as $b)
                            @php $tierColor = ['bronze'=>'amber','silver'=>'slate','gold'=>'yellow','platinum'=>'indigo'][$b->tier] ?? 'slate'; @endphp
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 text-2xl">{{ $b->icon }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $b->code }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $b->name }}<br><span class="text-xs text-slate-400">{{ $b->description }}</span></td>
                                <td class="px-3 py-2"><span class="inline-block rounded-full bg-{{ $tierColor }}-100 text-{{ $tierColor }}-700 px-2 py-0.5 text-xs font-semibold">{{ $b->tier }}</span></td>
                                <td class="px-3 py-2 text-xs">{{ $b->criterion_type }}</td>
                                <td class="px-3 py-2 font-bold">{{ number_format($b->threshold) }}</td>
                                <td class="px-3 py-2">
                                    @if ($b->is_active)
                                        <span class="inline-block rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold">Active</span>
                                    @else
                                        <span class="inline-block rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-semibold">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right space-x-1">
                                    <button wire:click="openEdit({{ $b->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Éditer</button>
                                    <button wire:click="toggleActive({{ $b->id }})" class="text-slate-600 hover:underline text-xs font-semibold">Toggle</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-8 text-center text-slate-400">Aucun badge. Créez-en un ou lancez le seeder ProviderBadgesSeeder.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                <div class="p-3">{{ $badges->links() }}</div>
            </div>
        @endif

        @if ($tab === 'awards')
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="brio-table-cadre"><table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Provider</th>
                            <th class="px-3 py-2">Badge</th>
                            <th class="px-3 py-2">Valeur</th>
                            <th class="px-3 py-2">Awarded at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($awards as $a)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2">{{ $a->provider?->name }}<br><span class="text-xs text-slate-400">{{ $a->provider?->email }}</span></td>
                                <td class="px-3 py-2">{{ $a->badge?->icon }} <span class="font-semibold">{{ $a->badge?->name }}</span></td>
                                <td class="px-3 py-2 font-bold">{{ number_format($a->value_at_award ?? 0) }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $a->awarded_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-slate-400">Aucun award.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                <div class="p-3">{{ $awards->links() }}</div>
            </div>
        @endif

        @if ($showForm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40">
                <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-slate-900">{{ $editBadgeId ? 'Éditer' : 'Nouveau' }} badge</h2>
                        <button wire:click="closeForm" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">×</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-slate-600" for="form_code">Code unique</label>
                            <input id="form_code" wire:model="form_code" type="text" class="w-full rounded-lg border-slate-300 text-sm">
                            @error('form_code') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600" for="form_icon">Icon (emoji)</label>
                            <input id="form_icon" wire:model="form_icon" type="text" maxlength="4" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-slate-600" for="form_name">Nom</label>
                            <input id="form_name" wire:model="form_name" type="text" class="w-full rounded-lg border-slate-300 text-sm">
                            @error('form_name') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-slate-600" for="form_description">Description</label>
                            <textarea id="form_description" wire:model="form_description" rows="2" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600" for="form_tier">Tier</label>
                            <select id="form_tier" wire:model="form_tier" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="bronze">Bronze</option>
                                <option value="silver">Silver</option>
                                <option value="gold">Gold</option>
                                <option value="platinum">Platinum</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600" for="form_criterion_type">Critère</label>
                            <select id="form_criterion_type" wire:model="form_criterion_type" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="missions_count">missions_count</option>
                                <option value="rating_avg">rating_avg (×100, ex: 450 = 4.5★)</option>
                                <option value="tips_received">tips_received</option>
                                <option value="tenure_days">tenure_days</option>
                                <option value="loyalty_points">loyalty_points</option>
                                <option value="streak_5stars">streak_5stars</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600" for="form_threshold">Seuil</label>
                            <input id="form_threshold" wire:model="form_threshold" type="number" min="1" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="flex items-end gap-2">
                            <input wire:model="form_is_active" type="checkbox" id="isActive" class="rounded border-slate-300">
                            <label for="isActive" class="text-sm">Actif</label>
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
