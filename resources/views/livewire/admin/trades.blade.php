<div class="space-y-6">
    {{-- ──────────────────────────────────────────────── --}}
    {{-- Header                                            --}}
    {{-- ──────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Corps de métier
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Catégories racines de la marketplace : Nettoyage, Bâtiment, Peinture, Levage…
                Chaque service du catalogue appartient à un métier.
            </p>
        </div>

        <button
            type="button"
            wire:click="openCreate"
            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
            </svg>
            Nouveau métier
        </button>
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
    {{-- Filtres                                           --}}
    {{-- ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div class="md:col-span-2">
            <label class="sr-only" for="search">Rechercher</label>
            <input
                type="search"
                id="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Rechercher un métier (nom, slug, code)…"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
        </div>
        <div>
            <select
                wire:model.live="status"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
                <option value="">Tous les statuts</option>
                <option value="active">Actifs uniquement</option>
                <option value="inactive">Inactifs uniquement</option>
            </select>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────── --}}
    {{-- Tableau                                           --}}
    {{-- ──────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ordre</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Métier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Services</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Drapeaux</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($trades as $trade)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 align-middle">
                            <div class="flex flex-col items-center">
                                <button wire:click="moveUp({{ $trade->id }})" class="text-gray-400 hover:text-blue-600" title="Monter">▲</button>
                                <span class="text-xs font-mono text-gray-500">{{ $trade->sort_order }}</span>
                                <button wire:click="moveDown({{ $trade->id }})" class="text-gray-400 hover:text-blue-600" title="Descendre">▼</button>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white text-sm font-semibold"
                                    style="background:{{ $trade->color ?: '#6B7280' }}"
                                >
                                    {{ strtoupper(mb_substr($trade->name, 0, 2)) }}
                                </span>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $trade->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $trade->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-middle font-mono text-xs text-gray-600 dark:text-gray-300">
                            {{ $trade->code }}
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                {{ $trade->services_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div class="flex flex-wrap gap-1">
                                @if ($trade->requires_certification)
                                    <span class="inline-flex items-center rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800" title="Certification requise">CERT</span>
                                @endif
                                @if ($trade->requires_insurance_proof)
                                    <span class="inline-flex items-center rounded bg-purple-100 px-2 py-0.5 text-xs text-purple-800" title="Assurance requise">RC</span>
                                @endif
                                @if ($trade->is_b2b_default)
                                    <span class="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">B2B</span>
                                @endif
                                @if ($trade->is_personal_default)
                                    <span class="inline-flex items-center rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">B2C</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <button
                                wire:click="toggleActive({{ $trade->id }})"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition
                                    {{ $trade->is_active
                                        ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                            >
                                {{ $trade->is_active ? 'Actif' : 'Inactif' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 align-middle text-right">
                            <div class="flex justify-end gap-2">
                                <button
                                    wire:click="edit({{ $trade->id }})"
                                    class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Éditer
                                </button>
                                @if ($trade->services_count === 0)
                                    <button
                                        wire:click="delete({{ $trade->id }})"
                                        wire:confirm="Supprimer ce métier ? (action soft-delete)"
                                        class="rounded border border-red-300 px-3 py-1 text-xs text-red-700 hover:bg-red-50"
                                    >
                                        Suppr.
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            Aucun métier {{ $search ? 'correspond à votre recherche.' : 'créé. Lance d’abord <code>php artisan db:seed --class=TradeSeeder</code>.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $trades->links() }}</div>

    {{-- ──────────────────────────────────────────────── --}}
    {{-- Modal Form (create/edit)                          --}}
    {{-- ──────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeForm">
            <div class="relative w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $tradeId ? 'Modifier le métier' : 'Nouveau métier' }}
                    </h2>
                    <button wire:click="closeForm" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit.prevent="save" class="space-y-4 px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Nom *</label>
                            <input type="text" wire:model.live.debounce.500ms="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Slug *</label>
                            <input type="text" wire:model="slug" class="mt-1 block w-full rounded-md border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            @error('slug') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Code *</label>
                            <input type="text" wire:model="code" class="mt-1 block w-full rounded-md border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            @error('code') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Ordre d'affichage</label>
                            <input type="number" wire:model="sort_order" min="0" max="9999" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Icône (nom Heroicon ou clé interne)</label>
                            <input type="text" wire:model="icon" placeholder="ex: broom, hammer, paint-brush" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Couleur (HEX)</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" wire:model="color" class="h-9 w-14 rounded border border-gray-300"/>
                                <input type="text" wire:model="color" class="block flex-1 rounded-md border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Description courte</label>
                        <input type="text" wire:model="short_description" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Description complète</label>
                        <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
                    </div>

                    <fieldset class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-200">Tarification & SLA</legend>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Tarif horaire par défaut (€)</label>
                                <input type="number" step="0.01" min="0" wire:model="default_hourly_rate" placeholder="Ex: 45.00"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('default_hourly_rate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Multiplicateur urgence (ASAP)</label>
                                <input type="number" step="0.01" min="1" max="10" wire:model="emergency_multiplier"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('emergency_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Multiplicateur nuit (22h-6h)</label>
                                <input type="number" step="0.01" min="1" max="10" wire:model="night_multiplier"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('night_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Multiplicateur weekend</label>
                                <input type="number" step="0.01" min="1" max="10" wire:model="weekend_multiplier"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('weekend_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Validité devis (jours)</label>
                                <input type="number" min="1" max="365" wire:model="quote_validity_days" placeholder="Ex: 30"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('quote_validity_days') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Délai de réponse SLA (min)</label>
                                <input type="number" min="1" max="43200" wire:model="sla_response_minutes" placeholder="Ex: 60"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('sla_response_minutes') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="requires_quote_by_default" class="rounded text-blue-600"/>
                                    <span class="text-sm text-gray-700 dark:text-gray-200">Devis obligatoire par défaut (le service ne peut pas être réservé directement)</span>
                                </label>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Les multiplicateurs urgence/nuit/weekend s'appliquent au prix de base quand le contexte de la mission le justifie.
                            Laissez à 1.00 pour ne pas appliquer de surcoût.
                        </p>
                    </fieldset>

                    <fieldset class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-200">Formulaire dynamique (schema JSON)</legend>
                        <p class="text-xs text-gray-500 mb-2">
                            Décris ici les champs que le client doit remplir pour ce métier (alternative aux champs cleaning hardcodés).
                            Si laissé vide, le formulaire legacy est utilisé.
                            Types supportés : <code>number</code>, <code>boolean</code>, <code>select</code>, <code>multiselect</code>, <code>text</code>, <code>textarea</code>.
                            Voir la documentation de <code>App\Support\TradeFormSchema</code> pour la structure complète.
                        </p>

                        <textarea
                            wire:model.live.debounce.500ms="booking_form_schema_json"
                            rows="12"
                            placeholder='&#123;"version": 1, "fields": [&#10;  &#123;"key": "nb_enfants", "label": "Nombre d\\u0027enfants", "type": "number", "required": true, "min": 1, "max": 10, "pricing": &#123;"modifier": "per_unit", "value": 5&#125;&#125;&#10;]&#125;'
                            class="block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
                        @error('booking_form_schema_json')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-2 flex justify-end">
                            <button type="button" wire:click="toggleFormSchemaPreview"
                                    class="text-xs px-3 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200">
                                {{ $showFormSchemaPreview ? 'Masquer l\'aperçu' : '👁 Aperçu interactif' }}
                            </button>
                        </div>

                        @if($showFormSchemaPreview)
                            <div class="mt-3 rounded-md border border-slate-200 p-3 bg-slate-50">
                                <livewire:admin.trade-form-preview
                                    :schema-input="$booking_form_schema_json"
                                    :base-price="100.0"
                                    :key="'preview-'.$tradeId.'-'.md5($booking_form_schema_json)" />
                            </div>
                        @endif
                    </fieldset>

                    <fieldset class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-200">Drapeaux opérationnels</legend>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_active" class="rounded text-blue-600"/> <span>Actif</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="requires_certification" class="rounded text-amber-600"/> <span>Certification requise (ex: CACES)</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="requires_insurance_proof" class="rounded text-purple-600"/> <span>Assurance pro requise</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_b2b_default" class="rounded text-blue-600"/> <span>Disponible B2B par défaut</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_personal_default" class="rounded text-green-600"/> <span>Disponible particuliers par défaut</span></label>
                        </div>
                    </fieldset>

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <button type="button" wire:click="closeForm" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Annuler</button>
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            {{ $tradeId ? 'Enregistrer' : 'Créer le métier' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
