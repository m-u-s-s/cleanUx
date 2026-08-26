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
        <div role="alert" class="brio-alerte brio-alerte-success">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div role="alert" class="brio-alerte brio-alerte-danger">
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
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tarif base (c)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Surge x</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Prix min (c)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Prix max (c)</th>
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
                                <div class="text-xs text-gray-500 mt-0.5">@if ($form_base_rate_cents > 0)<x-money :amount="(int) $form_base_rate_cents / 100" :currency="$zp->serviceZone?->deviseDeLaZone()" />@endif</div>
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

                                @if ($factureALHeure)
                                    {{--
                                        LE TARIF HORAIRE DE CETTE ZONE.

                                        Vide veut dire « suivre le tarif du métier », et c'est le cas
                                        courant. Zéro veut dire « une heure est offerte ici » — deux
                                        réponses distinctes, que le résolveur distingue déjà.
                                    --}}
                                    <label class="mt-2 block">
                                        <span class="block text-[10px] uppercase tracking-wide text-gray-500">{{ app(\App\Services\Localization\Money::class)->symbol($zp->serviceZone?->deviseDeLaZone() ?? \App\View\Components\Money::deviseDuContexte()) }}/heure de cette zone (c)</span>
                                        <input type="number" min="0" max="9999900" wire:model="form_price_per_hour_cents" placeholder="Tarif du métier"
                                            class="w-full rounded border-gray-300 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                    </label>
                                    @error('form_price_per_hour_cents') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                @endif

                                {{--
                                    LE PRIX AU KILOMÈTRE, replié sous l'activation.

                                    Il ne sert qu'aux métiers de trajet, et l'immense majorité des
                                    lignes n'en aura jamais besoin — mais l'administrateur qui ouvre
                                    une course dans une nouvelle zone doit le trouver LÀ OÙ il règle
                                    déjà le tarif, pas sur un troisième écran.
                                --}}
                                <label class="mt-2 inline-flex items-center gap-1.5">
                                    <input type="checkbox" wire:model.live="form_distance_pricing_enabled" class="rounded text-emerald-600"/>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Prix au km</span>
                                </label>

                                @if ($form_distance_pricing_enabled)
                                    <div class="mt-2 grid grid-cols-2 gap-1">
                                        <label class="block">
                                            <span class="block text-[10px] uppercase tracking-wide text-gray-500">Prise en charge (c)</span>
                                            <input type="number" min="0" wire:model="form_pickup_fee_cents"
                                                class="w-full rounded border-gray-300 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                        </label>
                                        <label class="block">
                                            <span class="block text-[10px] uppercase tracking-wide text-gray-500">Km inclus</span>
                                            <input type="number" min="0" wire:model="form_included_km"
                                                class="w-full rounded border-gray-300 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                        </label>
                                        <label class="block">
                                            <span class="block text-[10px] uppercase tracking-wide text-gray-500">c / km</span>
                                            <input type="number" min="0" wire:model="form_price_per_km_cents" placeholder="—"
                                                class="w-full rounded border-gray-300 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                        </label>
                                        <label class="block">
                                            <span class="block text-[10px] uppercase tracking-wide text-gray-500">c / min</span>
                                            <input type="number" min="0" wire:model="form_price_per_minute_cents" placeholder="—"
                                                class="w-full rounded border-gray-300 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                        </label>
                                    </div>
                                    @error('form_price_per_km_cents') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                @endif
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
                                <x-money :amount="(float) ($zp->base_rate_cents / 100)" :currency="$zp->serviceZone?->deviseDeLaZone()" />
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-800 dark:text-gray-200">
                                {{ $zp->surge_multiplier }}
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-600 dark:text-gray-400">
                                @if ($zp->min_price_cents !== null)<x-money :amount="$zp->min_price_cents / 100" :currency="$zp->serviceZone?->deviseDeLaZone()" />@else—@endif
                            </td>
                            <td class="px-4 py-3 align-middle tabular-nums text-gray-600 dark:text-gray-400">
                                @if ($zp->max_price_cents !== null)<x-money :amount="$zp->max_price_cents / 100" :currency="$zp->serviceZone?->deviseDeLaZone()" />@else—@endif
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

                                {{-- Même raison que le prix au kilomètre : un tarif horaire propre à
                                     la zone décide du montant final, il doit se voir sans ouvrir
                                     l'édition. Sans cette ligne, une surcharge saisie une fois
                                     devenait invisible et personne ne savait plus quelle zone en
                                     portait une. --}}
                                @if ($factureALHeure && $zp->price_per_hour_cents !== null)
                                    <span class="mt-1 block text-[11px] text-indigo-700 dark:text-indigo-400">
                                        <x-money :amount="(float) ($zp->price_per_hour_cents / 100)" />/h
                                    </span>
                                @endif

                                {{-- Un tarif au kilomètre actif doit se VOIR sans ouvrir l'édition :
                                     c'est lui qui décide du montant final sur une course. --}}
                                @if ($zp->distance_pricing_enabled)
                                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-400">
                                        <x-money :amount="(float) ($zp->pickup_fee_cents / 100)" />
                                        @if ($zp->price_per_km_cents !== null)
                                            + <x-money :amount="(float) ($zp->price_per_km_cents / 100)" />/km
                                        @endif
                                        @if ($zp->price_per_minute_cents !== null)
                                            + <x-money :amount="(float) ($zp->price_per_minute_cents / 100)" />/min
                                        @endif
                                        @if ($zp->included_km > 0)
                                            ({{ $zp->included_km }} km inclus)
                                        @endif
                                    </span>
                                @endif
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
