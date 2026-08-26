<div class="py-6">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        @unless($selectionMode)
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-bold uppercase text-indigo-600">Marketplace</p>
                    <h1 class="text-3xl font-black text-slate-900">Trouver une société prestataire</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Filtrez par nom, note, nombre de prestataires — comparez en un coup d'œil.
                    </p>
                </div>
            </div>
        @endunless

        <div class="grid grid-cols-1 gap-6 {{ $selectionMode ? '' : 'lg:grid-cols-4' }}">

            {{-- Sidebar filtres : masquée en mode picker condensé sur mobile,
                 visible en colonne hors picker. --}}
            <aside class="{{ $selectionMode ? '' : 'lg:col-span-1' }} space-y-4">
                <div class="brio-card space-y-3 p-4">
                    <h2 class="text-sm font-bold uppercase text-slate-500">Filtres</h2>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Recherche</label>
                        <input type="text" wire:model.live.debounce.400ms="query"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                               placeholder="Nom de la société..." />
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Note minimale</label>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach([null, 3, 4, 4.5] as $val)
                                <button type="button"
                                        wire:click="$set('minRating', @js($val))"
                                        class="rounded-lg border px-2 py-1 text-xs font-semibold {{ $minRating == $val ? 'border-indigo-600 bg-indigo-600 text-white' : 'bg-white text-slate-700' }}">
                                    {{ $val === null ? 'Tous' : $val . '★+' }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Trier par</label>
                        <select wire:model.live="sort" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="rating">Meilleure note</option>
                            <option value="providers">Plus de prestataires</option>
                            <option value="name">Nom (A→Z)</option>
                        </select>
                    </div>

                    <button wire:click="resetFilters"
                            class="w-full rounded-xl border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Réinitialiser
                    </button>
                </div>
            </aside>

            {{-- Résultats --}}
            <div class="{{ $selectionMode ? '' : 'lg:col-span-3' }} space-y-4">
                <p class="text-sm text-slate-600">
                    <span class="font-bold">{{ $companies->count() }}</span> société(s) trouvée(s)
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($companies as $org)
                        <div class="brio-card flex flex-col p-4">
                            <div class="flex items-start gap-3 {{ $selectionMode ? 'cursor-pointer' : '' }}"
                                 @if($selectionMode) wire:click="selectCompany({{ $org->id }})" @endif>
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-lg font-bold text-indigo-700">
                                    {{ strtoupper(mb_substr((string) $org->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-bold text-slate-900">{{ $org->name }}</p>
                                    @if($org->rating_avg !== null)
                                        <p class="mt-1 text-sm">
                                            <span class="text-amber-400">★</span>
                                            <span class="font-bold">{{ number_format((float) $org->rating_avg, 1, ',', ' ') }}</span>
                                            <span class="text-xs text-slate-500">({{ (int) ($org->rating_count ?? 0) }} avis)</span>
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-slate-400">Aucun avis</p>
                                    @endif
                                </div>
                            </div>

                            @if(($org->providers_count ?? null) !== null)
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <x-ui.badge tone="info" icon="👥">
                                        {{ (int) $org->providers_count }} prestataire(s)
                                    </x-ui.badge>
                                </div>
                            @endif

                            @if($selectionMode)
                                <button type="button"
                                        wire:click="selectCompany({{ $org->id }})"
                                        class="brio-btn-primary mt-4 w-full justify-center text-sm">
                                    Choisir cette société
                                </button>
                            @endif
                        </div>
                    @empty
                        <div class="md:col-span-2 xl:col-span-3">
                            <x-ui.empty-state
                                icon="🏢"
                                title="Aucune société prestataire"
                                message="Aucune société ne correspond à vos filtres pour le moment." />
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
