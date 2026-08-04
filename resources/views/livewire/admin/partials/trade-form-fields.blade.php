{{--
    Les champs d'un métier — la SEULE définition, incluse par les deux écrans qui les affichent :
    la gestion des métiers (/admin/trades) et le catalogue d'une zone.

    Un métier porte vingt et un champs, dont des multiplicateurs tarifaires et un schéma de
    questionnaire en JSON. Deux copies de ce balisage auraient divergé au premier champ ajouté, et
    l'écran oublié aurait continué d'enregistrer des métiers incomplets — sans erreur, puisque les
    colonnes absentes prennent leur valeur par défaut.

    Les propriétés visées par `wire:model` viennent du trait `ManagesTradeForm`, que les deux
    composants emploient.
--}}
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

