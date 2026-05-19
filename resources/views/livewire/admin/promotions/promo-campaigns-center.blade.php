<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Promotions</p>
                <h1 class="text-2xl font-black text-slate-900">Campagnes promo</h1>
                <p class="text-sm text-slate-500">Groupez vos codes promo par campagne marketing et fixez un budget.</p>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.promotions.codes') }}"
                   class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    → Codes
                </a>
                <a href="{{ route('admin.dashboard') }}"
                   class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    ← Dashboard
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $editingId ? 'Modifier la campagne' : 'Nouvelle campagne' }}
                    </h2>
                    @if($editingId)
                        <button wire:click="resetForm" class="text-xs text-slate-500 hover:underline">Annuler</button>
                    @endif
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Nom</label>
                    <input type="text" wire:model.live.debounce.500ms="name"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Slug</label>
                    <input type="text" wire:model="slug"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm font-mono" />
                    @error('slug') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Description</label>
                    <textarea wire:model="description" rows="2"
                              class="mt-1 w-full rounded-xl border-gray-300 text-sm"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Début</label>
                        <input type="datetime-local" wire:model="starts_at"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Fin</label>
                        <input type="datetime-local" wire:model="ends_at"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Budget plafond</label>
                    <input type="number" step="0.01" wire:model="budget_cap"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="∞" />
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Audience cible (libre)</label>
                    <input type="text" wire:model="target_audience"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="Ex: étudiants Bruxelles" />
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Statut</label>
                    <select wire:model="status" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="draft">Brouillon</option>
                        <option value="scheduled">Planifiée</option>
                        <option value="active">Active</option>
                        <option value="paused">En pause</option>
                        <option value="archived">Archivée</option>
                    </select>
                </div>

                <button wire:click="save"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ $editingId ? 'Enregistrer' : 'Créer la campagne' }}
                </button>
            </div>

            <div class="lg:col-span-2 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Campagnes</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Nom</th>
                                <th class="px-3 py-2 text-left">Codes</th>
                                <th class="px-3 py-2 text-left">Budget</th>
                                <th class="px-3 py-2 text-left">Période</th>
                                <th class="px-3 py-2 text-left">Statut</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($campaigns as $c)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-bold text-slate-900">{{ $c->name }}</div>
                                        <div class="text-xs text-slate-500 font-mono">{{ $c->slug }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $c->promo_codes_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">
                                        @if($c->budget_cap)
                                            {{ number_format((float)$c->total_discounted, 2, ',', ' ') }}
                                            / {{ number_format((float)$c->budget_cap, 2, ',', ' ') }} €
                                        @else
                                            illimité
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        {{ optional($c->starts_at)->format('d/m/Y') ?? '—' }} →
                                        {{ optional($c->ends_at)->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => $c->status === 'active',
                                            'bg-amber-100 text-amber-800' => $c->status === 'paused',
                                            'bg-slate-100 text-slate-700' => in_array($c->status, ['draft','scheduled','archived']),
                                        ])>{{ $c->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right space-x-1">
                                        <button wire:click="edit({{ $c->id }})"
                                                class="text-xs font-semibold text-indigo-600 hover:underline">Modifier</button>
                                        @if($c->status === 'active')
                                            <button wire:click="pause({{ $c->id }})"
                                                    class="text-xs font-semibold text-amber-600 hover:underline">Pause</button>
                                        @else
                                            <button wire:click="activate({{ $c->id }})"
                                                    class="text-xs font-semibold text-emerald-600 hover:underline">Activer</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Aucune campagne.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $campaigns->links() }}</div>
            </div>
        </div>
    </div>
</div>
