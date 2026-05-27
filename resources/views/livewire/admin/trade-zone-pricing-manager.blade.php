<div class="space-y-6">

    {{-- ──────────────────────────────────────────────── --}}
    {{-- Header                                            --}}
    {{-- ──────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                <a href="{{ route('admin.trades') }}" class="hover:underline">Corps de métier</a>
                <span>/</span>
                <span class="text-gray-700 dark:text-gray-200 font-medium">{{ $tradeName }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Tarification par zone
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Configurez le tarif de base (centimes), le multiplicateur de surge et les planchers/plafonds pour chaque zone de service.
            </p>
        </div>

        {{-- Add zone dropdown --}}
        @if ($availableZones->isNotEmpty())
            <div class="flex items-center gap-2">
                <select
                    wire:model="addZoneId"
                    class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm"
                >
                    <option value="">-- Ajouter une zone --</option>
                    @foreach ($availableZones as $zone)
                        <option value="{{ $zone->id }}">
                            {{ $zone->name }}{{ $zone->code ? ' (' . $zone->code . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                <button
                    type="button"
                    wire:click="addZone({{ (int) $addZoneId }})"
                    @disabled(! $addZoneId)
                    class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
                    </svg>
                    Ajouter
                </button>
            </div>
        @endif
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-md border-l-4 border-green-400 bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-md border-l-4 border-red-400 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- ──────────────────────────────────────────────── --}}
    {{-- Table                                             --}}
    {{-- ──────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Zone</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tarif base (€)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Surge x</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Prix min (€)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Prix max (€)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actif</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($zonePricings as $zp)
                    @if ($editingId === $zp->id)
                        {{-- ── Inline edit row ── --}}
                        <tr class="bg-blue-50 dark:bg-blue-900/20">
                            <td class="px-4 py-3 align-middle font-medium text-gray-900 dark:text-white">
                                {{ $zp->serviceZone?->name ?? '—' }}
                                @if ($zp->serviceZone?->code)
                                    <span class="ml-1 font-mono text-xs text-gray-500">({{ $zp->serviceZone->code }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <input
                                    type="number"
                                    wire:model="form_base_rate_cents"
                                    min="0"
                                    max="9999900"
                                    class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    placeholder="ex: 4500"
                                />
                                <div class="text-xs text-gray-500 mt-0.5">{{ $form_base_rate_cents > 0 ? number_format((int)$form_base_rate_cents / 100, 2) . ' €' : '' }}</div>
                                @error('form_base_rate_cents') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <input
                                    type="number"
                                    wire:model="form_surge_multiplier"
                                    min="1"
                                    max="10"
                                    step="0.01"
                                    class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                />
                                @error('form_surge_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <input
                                    type="number"
                                    wire:model="form_min_price_cents"
                                    min="0"
                                    max="9999900"
                                    class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    placeholder="—"
                                />
                                @error('form_min_price_cents') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <input
                                    type="number"
                                    wire:model="form_max_price_cents"
                                    min="0"
                                    max="9999900"
                                    class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    placeholder="—"
                                />
                                @error('form_max_price_cents') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <label class="inline-flex items-center gap-1.5">
                                    <input type="checkbox" wire:model="form_is_active" class="rounded text-blue-600"/>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Actif</span>
                                </label>
                            </td>
                            <td class="px-4 py-3 align-middle text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        wire:click="save"
                                        class="rounded border border-blue-500 bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700"
                                    >
                                        Enregistrer
                                    </button>
                                    <button
                                        wire:click="cancelEdit"
                                        class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Annuler
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @else
                        {{-- ── Read-only row ── --}}
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 align-middle font-medium text-gray-900 dark:text-white">
                                {{ $zp->serviceZone?->name ?? '—' }}
                                @if ($zp->serviceZone?->code)
                                    <span class="ml-1 font-mono text-xs text-gray-500">({{ $zp->serviceZone->code }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-800 dark:text-gray-200">
                                {{ number_format($zp->base_rate_cents / 100, 2) }} €
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-800 dark:text-gray-200">
                                {{ $zp->surge_multiplier }}
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-600 dark:text-gray-400">
                                {{ $zp->min_price_cents !== null ? number_format($zp->min_price_cents / 100, 2) . ' €' : '—' }}
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-600 dark:text-gray-400">
                                {{ $zp->max_price_cents !== null ? number_format($zp->max_price_cents / 100, 2) . ' €' : '—' }}
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <button
                                    wire:click="toggleActive({{ $zp->id }})"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition
                                        {{ $zp->is_active
                                            ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                                >
                                    {{ $zp->is_active ? 'Actif' : 'Inactif' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 align-middle text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        wire:click="edit({{ $zp->id }})"
                                        class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Editer
                                    </button>
                                    <button
                                        wire:click="delete({{ $zp->id }})"
                                        wire:confirm="Supprimer ce tarif zone ?"
                                        class="rounded border border-red-300 px-3 py-1 text-xs text-red-700 hover:bg-red-50"
                                    >
                                        Suppr.
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            Aucune zone configurée pour ce métier.
                            @if ($availableZones->isNotEmpty())
                                Utilisez le menu ci-dessus pour en ajouter une.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Back link --}}
    <div>
        <a
            href="{{ route('admin.trades') }}"
            class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            Retour aux corps de métier
        </a>
    </div>

</div>
