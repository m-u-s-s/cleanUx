<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">i18n v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre de traductions</h1>
                <p class="text-sm text-slate-500">
                    Éditez les traductions en DB (override fichier disque, sans déploiement).
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Clés totales ({{ $locale }})</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['total_keys']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Overrides DB</p>
                <p class="text-2xl font-black text-indigo-600">{{ $kpis['overrides_count'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Langues disponibles</p>
                <p class="text-2xl font-black text-emerald-600">{{ count($kpis['available_locales']) }}</p>
            </div>
        </div>

        {{-- Locale + group selectors --}}
        <div class="rounded-2xl border bg-white p-4 shadow-sm flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs font-bold uppercase text-slate-500">Langue</label>
                <select wire:model.live="locale" class="mt-1 rounded-xl border-gray-300 text-sm">
                    @foreach($kpis['available_locales'] as $l)
                        <option value="{{ $l['code'] }}">{{ $l['flag'] }} {{ $l['native_name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold uppercase text-slate-500">Groupe</label>
                <select wire:model.live="group" class="mt-1 rounded-xl border-gray-300 text-sm">
                    @foreach($groups as $g)
                        <option value="{{ $g }}">{{ $g }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs font-bold uppercase text-slate-500">Recherche</label>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Clé ou valeur..."
                       class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="showOnlyOverridden" class="rounded" />
                Overrides uniquement
            </label>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left w-1/4">Clé</th>
                        <th class="px-4 py-2 text-left w-1/3">Valeur ({{ $locale }})</th>
                        <th class="px-4 py-2 text-left w-1/3">Fallback (EN)</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse(array_slice($rows, 0, 100) as $row)
                        <tr class="{{ ! empty($row['overridden']) ? 'bg-indigo-50' : '' }}">
                            <td class="px-4 py-2 font-mono text-xs text-slate-700">
                                {{ $row['short_key'] }}
                                @if(! empty($row['overridden']))
                                    <span class="ml-1 inline-flex items-center rounded-full bg-indigo-100 px-1.5 text-[10px] font-bold text-indigo-800">DB</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if($editingKey === $row['key'])
                                    <div class="flex flex-col gap-2">
                                        <textarea wire:model="editingValue" rows="2"
                                                  class="w-full rounded-xl border-gray-300 text-sm"></textarea>
                                        @error('editingValue') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="flex gap-2">
                                            <button wire:click="saveOverride"
                                                    class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">
                                                Enregistrer
                                            </button>
                                            <button wire:click="cancelEdit"
                                                    class="rounded-lg border px-3 py-1 text-xs">
                                                Annuler
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-800">{{ $row['value'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-500 italic text-xs">
                                {{ $row['fallback'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right space-x-2">
                                @if($editingKey !== $row['key'])
                                    <button wire:click="startEdit('{{ $row['key'] }}', @js($row['value']))"
                                            class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Éditer
                                    </button>
                                    @if(! empty($row['overridden']))
                                        <button wire:click="removeOverride('{{ $row['key'] }}')"
                                                wire:confirm="Supprimer cet override DB ?"
                                                class="text-xs font-semibold text-red-600 hover:underline">
                                            Reset
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">
                            Aucune clé ne correspond.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
            @if(count($rows) > 100)
                <div class="p-3 text-xs text-slate-500 text-center">
                    Affichage limité à 100 clés. Affinez la recherche pour voir d'autres résultats.
                </div>
            @endif
        </div>
    </div>
</div>
