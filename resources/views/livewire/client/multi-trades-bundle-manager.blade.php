<div class="py-8 max-w-5xl mx-auto px-4">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <p class="ui-page-eyebrow !mt-0">Multi-métiers</p>
            <h1 class="ui-page-title">Mes chantiers groupés</h1>
            <p class="ui-page-subtitle">
                Rénovation, déménagement, événement : commandez plusieurs prestations en 1 fois.
            </p>
        </div>

        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl shrink-0">
            <button wire:click="setTab('list')" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $tab === 'list' ? 'bg-white text-brand-700 shadow-soft-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Mes bundles
            </button>
            <button wire:click="setTab('create')" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $tab === 'create' ? 'bg-white text-brand-700 shadow-soft-sm' : 'text-slate-600 hover:text-slate-900' }}">
                <x-ui.icon name="plus" class="w-3 h-3" />
                Nouveau
            </button>
        </div>
    </div>

    @if ($tab === 'list')
        <div class="space-y-4">
            @forelse ($bundles as $b)
                @php
                    $color = ['draft'=>'slate','quoting'=>'amber','quoted'=>'indigo','accepted'=>'emerald','in_progress'=>'purple','completed'=>'emerald','cancelled'=>'rose'][$b->status] ?? 'slate';
                @endphp
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-soft-sm p-5 hover:shadow-soft transition">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <p class="text-xs font-mono text-slate-400">{{ $b->code }}</p>
                            <h2 class="text-base font-bold text-slate-900 mt-0.5">{{ $b->name }}</h2>
                            @if ($b->description)
                                <p class="text-sm text-slate-500 mt-1">{{ $b->description }}</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center rounded-full bg-{{ $color }}-50 border border-{{ $color }}-200 text-{{ $color }}-700 px-2.5 py-0.5 text-xs font-semibold shrink-0">
                            {{ $b->status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="rounded-lg border border-slate-200/70 bg-slate-50/50 p-2.5">
                            <p class="text-xs text-slate-500">Items</p>
                            <p class="mt-0.5 text-base font-bold text-slate-900">{{ $b->items->count() }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200/70 bg-slate-50/50 p-2.5">
                            <p class="text-xs text-slate-500">Estimé</p>
                            <p class="mt-0.5 text-base font-bold text-slate-900">{{ number_format($b->total_estimated_cents / 100, 0, ',', ' ') }} €</p>
                        </div>
                        <div class="rounded-lg border border-brand-200 bg-brand-50/40 p-2.5">
                            <p class="text-xs text-brand-700">Quoté</p>
                            <p class="mt-0.5 text-base font-bold text-brand-700">{{ number_format($b->total_quoted_cents / 100, 0, ',', ' ') }} €</p>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-3 mb-3 space-y-1.5">
                        @foreach ($b->items as $i => $item)
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-slate-600 inline-flex items-center gap-1 min-w-0">
                                    <span class="font-mono text-slate-400">{{ $i + 1 }}.</span>
                                    <strong class="text-slate-800 shrink-0">{{ $item->trade?->name ?? 'Trade #' . $item->trade_id }}</strong>
                                    <span class="text-slate-400">·</span>
                                    <span class="truncate">{{ $item->label }}</span>
                                    @if ($item->provider)
                                        <span class="ml-1 inline-flex items-center gap-1 text-emerald-700 shrink-0">
                                            <x-ui.icon name="user" class="w-3 h-3" />
                                            {{ $item->provider->name }}
                                        </span>
                                    @endif
                                </span>
                                <span class="font-semibold shrink-0 {{ $item->quoted_price_cents > 0 ? 'text-brand-700' : 'text-slate-400' }}">
                                    {{ number_format(($item->quoted_price_cents ?: $item->estimated_price_cents) / 100, 0, ',', ' ') }} €
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-2 justify-end">
                        @if ($b->status === 'draft')
                            <button wire:click="startQuoting({{ $b->id }})" class="cu-btn-primary !py-1.5 !text-xs inline-flex items-center gap-1.5">
                                <x-ui.icon name="document" class="w-3.5 h-3.5" />
                                Demander des devis
                            </button>
                        @endif
                        @if ($b->status === 'quoted')
                            <button wire:click="acceptBundle({{ $b->id }})"
                                    wire:confirm="Accepter ce bundle ? {{ $b->items->count() }} missions vont être créées."
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-emerald-700 transition">
                                <x-ui.icon name="check" class="w-3.5 h-3.5" />
                                Accepter
                            </button>
                        @endif
                        @if (in_array($b->status, ['draft', 'quoting', 'quoted']))
                            <button wire:click="cancelBundle({{ $b->id }})" wire:confirm="Annuler ce bundle ?"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white text-rose-600 px-3 py-1.5 text-xs font-semibold hover:bg-rose-50 transition">
                                <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                                Annuler
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center bg-white">
                    <div class="grid h-12 w-12 mx-auto place-items-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <x-ui.icon name="cube" class="w-6 h-6" />
                    </div>
                    <p class="text-slate-700 font-semibold">Aucun chantier groupé</p>
                    <p class="mt-1 text-sm text-slate-500">Créez votre premier bundle multi-métiers.</p>
                    <button wire:click="setTab('create')" class="cu-btn-primary mt-4 inline-flex items-center gap-2">
                        <x-ui.icon name="plus" class="w-4 h-4" />
                        Créer mon premier bundle
                    </button>
                </div>
            @endforelse
        </div>
    @endif

    @if ($tab === 'create')
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6">
            <div class="rounded-xl bg-brand-50/40 border border-brand-100 p-3.5 mb-5 inline-flex items-start gap-2 w-full">
                <x-ui.icon name="information-circle" class="w-4 h-4 mt-0.5 text-brand-600 flex-shrink-0" />
                <p class="text-sm text-slate-700">
                    <strong class="text-brand-700">Exemple</strong> : rénovation salle de bain = plombier + carreleur + peintre + électricien.
                    Sélectionnez chaque métier, décrivez la tâche, et CleanUx orchestre les missions dans le bon ordre.
                </p>
            </div>

            <div class="space-y-4 mb-6">
                <div>
                    <label class="ui-label">Nom du chantier *</label>
                    <input wire:model="bundleName" type="text" placeholder="ex: Rénovation salle de bain principale" class="ui-input">
                    @error('bundleName') <p class="ui-error-msg">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ui-label">Description (optionnel)</label>
                    <textarea wire:model="bundleDescription" rows="2" placeholder="Détails du projet, contraintes…" class="ui-input"></textarea>
                </div>
            </div>

            <div class="mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-slate-900">Prestations du chantier</h3>
                    <button wire:click="addItem" type="button" class="inline-flex items-center gap-1 text-xs text-brand-700 hover:text-brand-800 font-semibold">
                        <x-ui.icon name="plus" class="w-3.5 h-3.5" />
                        Ajouter
                    </button>
                </div>

                @foreach ($items as $idx => $item)
                    <div class="rounded-xl bg-slate-50/60 border border-slate-200/70 p-3.5 mb-2 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="inline-flex items-center gap-1 text-xs font-bold text-slate-500">
                                <span class="grid h-5 w-5 place-items-center rounded-full bg-brand-100 text-brand-700 text-[10px]">{{ $idx + 1 }}</span>
                                Prestation
                            </p>
                            @if (count($items) > 2)
                                <button wire:click="removeItem({{ $idx }})" type="button" class="inline-flex items-center gap-1 text-xs text-rose-600 hover:text-rose-700">
                                    <x-ui.icon name="x-mark" class="w-3 h-3" />
                                    Supprimer
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="ui-label !text-[11px]">Métier *</label>
                                <select wire:model="items.{{ $idx }}.trade_id" class="ui-input">
                                    <option value="">— Choisir —</option>
                                    @foreach ($trades as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="ui-label !text-[11px]">Libellé *</label>
                                <input wire:model="items.{{ $idx }}.label" type="text" placeholder="ex: Pose carrelage sol" class="ui-input">
                            </div>
                        </div>

                        <div>
                            <label class="ui-label !text-[11px]">Description</label>
                            <textarea wire:model="items.{{ $idx }}.description" rows="2" placeholder="Détails de la prestation…" class="ui-input"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="ui-label !text-[11px]">Durée (min)</label>
                                <input wire:model="items.{{ $idx }}.duration_minutes" type="number" min="15" step="15" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label !text-[11px]">Budget estimé (€) *</label>
                                <input wire:model="items.{{ $idx }}.estimated_price_eur" type="number" step="10" min="0" class="ui-input">
                            </div>
                        </div>
                    </div>
                @endforeach
                @error('items') <p class="ui-error-msg">{{ $message }}</p> @enderror
            </div>

            <button wire:click="createBundle" class="cu-btn-primary w-full inline-flex items-center justify-center gap-2">
                <x-ui.icon name="check" class="w-4 h-4" />
                Créer le chantier
            </button>
            <p class="text-xs text-slate-500 mt-3 text-center">
                Une fois créé, vous pourrez demander des devis aux providers spécialisés.
            </p>
        </div>
    @endif
</div>
