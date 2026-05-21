<div class="py-6 max-w-5xl mx-auto px-4">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Multi-métiers</p>
            <h1 class="text-2xl font-black text-slate-900">Mes chantiers groupés</h1>
            <p class="text-sm text-slate-500">Rénovation, déménagement, événement : commande plusieurs prestations en 1 fois.</p>
        </div>
        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl">
            <button wire:click="setTab('list')" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $tab === 'list' ? 'bg-white text-indigo-700 shadow' : 'text-slate-600' }}">
                Mes bundles
            </button>
            <button wire:click="setTab('create')" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $tab === 'create' ? 'bg-white text-indigo-700 shadow' : 'text-slate-600' }}">
                + Nouveau bundle
            </button>
        </div>
    </div>

    @if ($tab === 'list')
        <div class="space-y-4">
            @forelse ($bundles as $b)
                @php
                    $color = ['draft'=>'slate','quoting'=>'amber','quoted'=>'indigo','accepted'=>'emerald','in_progress'=>'purple','completed'=>'emerald','cancelled'=>'rose'][$b->status] ?? 'slate';
                @endphp
                <div class="rounded-2xl border bg-white shadow-sm p-5">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-3">
                        <div>
                            <p class="text-xs font-mono text-slate-400">{{ $b->code }}</p>
                            <h2 class="text-lg font-bold text-slate-900">{{ $b->name }}</h2>
                            @if ($b->description)
                                <p class="text-sm text-slate-500 mt-1">{{ $b->description }}</p>
                            @endif
                        </div>
                        <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-3 py-1 text-xs font-bold">{{ $b->status }}</span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-sm mb-4">
                        <div>
                            <p class="text-xs text-slate-500">Items</p>
                            <p class="font-bold">{{ $b->items->count() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Estimé</p>
                            <p class="font-bold">{{ number_format($b->total_estimated_cents / 100, 2, ',', ' ') }} €</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Quoté</p>
                            <p class="font-bold text-indigo-700">{{ number_format($b->total_quoted_cents / 100, 2, ',', ' ') }} €</p>
                        </div>
                    </div>

                    <div class="border-t pt-3 mb-3 space-y-1">
                        @foreach ($b->items as $i => $item)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">
                                    <span class="font-mono text-slate-400">{{ $i + 1 }}.</span>
                                    <strong>{{ $item->trade?->name ?? 'Trade #' . $item->trade_id }}</strong>
                                    — {{ $item->label }}
                                    @if ($item->provider)
                                        <span class="ml-2 text-emerald-600">👤 {{ $item->provider->name }}</span>
                                    @endif
                                </span>
                                <span class="font-bold {{ $item->quoted_price_cents > 0 ? 'text-indigo-700' : 'text-slate-400' }}">
                                    {{ number_format(($item->quoted_price_cents ?: $item->estimated_price_cents) / 100, 2, ',', ' ') }} €
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-2 justify-end">
                        @if ($b->status === 'draft')
                            <button wire:click="startQuoting({{ $b->id }})" class="rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-indigo-500">
                                Demander des devis
                            </button>
                        @endif
                        @if ($b->status === 'quoted')
                            <button wire:click="acceptBundle({{ $b->id }})" wire:confirm="Accepter ce bundle ? {{ $b->items->count() }} missions vont être créées." class="rounded-lg bg-emerald-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-emerald-500">
                                Accepter
                            </button>
                        @endif
                        @if (in_array($b->status, ['draft', 'quoting', 'quoted']))
                            <button wire:click="cancelBundle({{ $b->id }})" wire:confirm="Annuler ce bundle ?" class="rounded-lg border text-rose-600 px-3 py-1.5 text-xs font-semibold hover:bg-rose-50">
                                Annuler
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center">
                    <p class="text-5xl mb-3">🏗️</p>
                    <p class="text-slate-500 mb-2">Vous n'avez pas encore de chantier groupé.</p>
                    <button wire:click="setTab('create')" class="mt-3 inline-block rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
                        Créer mon premier bundle
                    </button>
                </div>
            @endforelse
        </div>
    @endif

    @if ($tab === 'create')
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <p class="text-sm text-slate-600 mb-4">
                💡 <strong>Exemple</strong> : rénovation salle de bain = plombier + carreleur + peintre + électricien.
                Sélectionnez chaque métier, décrivez ce qui doit être fait, et CleanUx orchestre les missions dans le bon ordre.
            </p>

            <div class="space-y-3 mb-6">
                <div>
                    <label class="text-xs font-bold text-slate-600">Nom du chantier *</label>
                    <input wire:model="bundleName" type="text" placeholder="ex: Rénovation salle de bain principale" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                    @error('bundleName') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600">Description (optionnel)</label>
                    <textarea wire:model="bundleDescription" rows="2" placeholder="Détails du projet, contraintes, etc." class="w-full rounded-lg border-slate-300 text-sm mt-1"></textarea>
                </div>
            </div>

            <div class="mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-slate-900">Prestations du chantier</h3>
                    <button wire:click="addItem" type="button" class="text-xs text-indigo-600 hover:underline font-semibold">+ Ajouter une prestation</button>
                </div>

                @foreach ($items as $idx => $item)
                    <div class="rounded-lg bg-slate-50 border p-3 mb-2 space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-slate-500">Prestation {{ $idx + 1 }}</p>
                            @if (count($items) > 2)
                                <button wire:click="removeItem({{ $idx }})" type="button" class="text-xs text-rose-600 hover:underline">Supprimer</button>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Métier *</label>
                                <select wire:model="items.{{ $idx }}.trade_id" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                                    <option value="">-- Choisir --</option>
                                    @foreach ($trades as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Libellé *</label>
                                <input wire:model="items.{{ $idx }}.label" type="text" placeholder="ex: Pose carrelage sol" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600">Description</label>
                            <textarea wire:model="items.{{ $idx }}.description" rows="2" placeholder="Détails de la prestation..." class="w-full rounded-lg border-slate-300 text-sm mt-1"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Durée (min)</label>
                                <input wire:model="items.{{ $idx }}.duration_minutes" type="number" min="15" step="15" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Budget estimé (€) *</label>
                                <input wire:model="items.{{ $idx }}.estimated_price_eur" type="number" step="10" min="0" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                            </div>
                        </div>
                    </div>
                @endforeach
                @error('items') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
            </div>

            <button wire:click="createBundle" class="w-full rounded-lg bg-indigo-600 text-white py-3 text-sm font-bold hover:bg-indigo-500">
                Créer le chantier
            </button>
            <p class="text-xs text-slate-500 mt-3 text-center">
                Une fois créé, vous pourrez demander des devis aux providers spécialisés dans chaque métier.
            </p>
        </div>
    @endif
</div>
