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
{{--
    L'ASSISTANT DU MÉTIER — « le formulaire du catalogue est trop long ».

    Vingt-quatre champs à plat, dont sept drapeaux et un schéma JSON : on remplissait en
    faisant défiler, sans jamais savoir combien il en restait. Les quatre groupes existaient
    déjà dans le balisage ; ils deviennent quatre étapes.

    AUCUN CHAMP NE QUITTE LE DOM. `x-show` masque, il ne démonte pas : toutes les liaisons
    `wire:model` restent actives, et l'enregistrement porte la totalité du formulaire quelle
    que soit l'étape affichée. Un assistant qui monterait ses étapes à la demande perdrait
    les valeurs saisies au premier retour en arrière.

    LES ERREURS REMONTENT DANS LE RAIL. Sans cela, un refus de validation sur l'identité,
    alors qu'on est rendu aux drapeaux, ressemblerait à un bouton qui ne fait rien.
--}}
@php
    $etapesDuMetier = [
        1 => ['titre' => __('Identité'), 'champs' => ['name', 'slug', 'code', 'sort_order', 'icon', 'color', 'short_description', 'description']],
        2 => ['titre' => __('Tarifs & délais'), 'champs' => ['default_hourly_rate', 'emergency_multiplier', 'night_multiplier', 'weekend_multiplier', 'quote_validity_days', 'sla_response_minutes', 'hourly_billing', 'requires_quote_by_default']],
        3 => ['titre' => __('Questionnaire'), 'champs' => ['booking_form_schema_json']],
        4 => ['titre' => __('Règles'), 'champs' => ['is_active', 'requires_certification', 'requires_insurance_proof', 'requires_face_check', 'is_personal_default', 'taxi_rules']],
    ];

    // L'étape ouverte au premier rendu est celle qui porte une erreur, sinon la première.
    $etapeInitiale = collect($etapesDuMetier)
        ->search(fn (array $e) => collect($e['champs'])->contains(fn (string $c) => $errors->has($c))) ?: 1;
@endphp

<div x-data="{ etape: @js($etapeInitiale) }" class="space-y-4">
    {{-- LE RAIL : où l'on en est, et où ça coince. --}}
    <ol class="brio-rail" role="list">
        @foreach ($etapesDuMetier as $numero => $etape)
            @php($enFaute = collect($etape['champs'])->contains(fn (string $c) => $errors->has($c)))
            <li>
                <button type="button"
                        class="brio-rail-etape"
                        :class="etape === {{ $numero }} && 'brio-rail-etape-active'"
                        x-on:click="etape = {{ $numero }}"
                        @if($enFaute) data-en-faute="1" @endif>
                    <span class="brio-rail-numero" aria-hidden="true">{{ $numero }}</span>
                    <span>{{ $etape['titre'] }}</span>
                    @if($enFaute)
                        <span class="sr-only">— {{ __('contient une erreur') }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ol>

                    <fieldset x-show="etape === 1" x-cloak class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Identité') }}</legend>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="name">Nom *</label>
                            <input id="name" type="text" wire:model.live.debounce.500ms="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="slug">Slug *</label>
                            <input id="slug" type="text" wire:model="slug" class="mt-1 block w-full rounded-md border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            @error('slug') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="code">Code *</label>
                            <input id="code" type="text" wire:model="code" class="mt-1 block w-full rounded-md border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            @error('code') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="sort_order">Ordre d'affichage</label>
                            <input id="sort_order" type="number" wire:model="sort_order" min="0" max="9999" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="icon">Icône (nom Heroicon ou clé interne)</label>
                            <input id="icon" type="text" wire:model="icon" placeholder="ex: broom, hammer, paint-brush" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="color">Couleur (HEX)</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input id="color" type="color" wire:model="color" class="h-9 w-14 rounded border border-gray-300"/>
                                <input type="text" wire:model="color" class="block flex-1 rounded-md border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="short_description">Description courte</label>
                        <input id="short_description" type="text" wire:model="short_description" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="description">Description complète</label>
                        <textarea id="description" wire:model="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
                    </div>
                    </fieldset>
                    <fieldset x-show="etape === 2" x-cloak class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-200">Tarification & SLA</legend>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="default_hourly_rate">Tarif horaire par défaut (€)</label>
                                <input id="default_hourly_rate" type="number" step="0.01" min="0" wire:model="default_hourly_rate" placeholder="Ex: 45.00"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('default_hourly_rate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="emergency_multiplier">Multiplicateur urgence (ASAP)</label>
                                <input id="emergency_multiplier" type="number" step="0.01" min="1" max="10" wire:model="emergency_multiplier"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('emergency_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="night_multiplier">Multiplicateur nuit (22h-6h)</label>
                                <input id="night_multiplier" type="number" step="0.01" min="1" max="10" wire:model="night_multiplier"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('night_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="weekend_multiplier">Multiplicateur weekend</label>
                                <input id="weekend_multiplier" type="number" step="0.01" min="1" max="10" wire:model="weekend_multiplier"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('weekend_multiplier') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="quote_validity_days">Validité devis (jours)</label>
                                <input id="quote_validity_days" type="number" min="1" max="365" wire:model="quote_validity_days" placeholder="Ex: 30"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('quote_validity_days') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="sla_response_minutes">Délai de réponse SLA (min)</label>
                                <input id="sla_response_minutes" type="number" min="1" max="43200" wire:model="sla_response_minutes" placeholder="Ex: 60"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"/>
                                @error('sla_response_minutes') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </div>
                            {{--
                                LA FACTURATION AU TEMPS PASSÉ.

                                Placée dans « Tarification & SLA » et non dans les drapeaux opérationnels :
                                ceux-là disent ce qu'on exige du PRESTATAIRE, celui-ci dit comment le service
                                est vendu au CLIENT — et il pilote le tarif horaire saisi juste au-dessus.

                                `wire:model.live` et non `wire:model` : la case doit faire apparaître
                                immédiatement l'aide qui la suit, sinon l'admin coche et ne voit rien changer.
                            --}}
                            <div class="md:col-span-3">
                                <label class="inline-flex items-start gap-2">
                                    <input type="checkbox" wire:model.live="hourly_billing" class="mt-1 rounded text-emerald-600"/>
                                    <span class="text-sm text-gray-700 dark:text-gray-200">
                                        <strong>Paiement à l'heure</strong> — le client choisit son nombre d'heures et paie
                                        le tarif horaire ci-dessus, au lieu d'un forfait.
                                    </span>
                                </label>
                                @error('hourly_billing') <span class="block text-xs text-red-600">{{ $message }}</span> @enderror

                                @if($hourly_billing)
                                    <p class="mt-2 rounded-md bg-emerald-50 p-2 text-xs text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
                                        Le tarif horaire devient obligatoire. Il peut être surchargé zone par zone depuis
                                        <em>Tarifs par zone</em>. Les heures dépassées sans prolongation du client sont
                                        facturées au tarif horaire &times;{{ number_format((float) config('order_engine.overtime_multiplier', 1.30), 2) }},
                                        après une franchise de {{ (int) config('order_engine.overtime_grace_minutes', 15) }} minutes.
                                    </p>
                                @endif
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
                    <fieldset x-show="etape === 3" x-cloak class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
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
                    <fieldset x-show="etape === 4" x-cloak class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-200">Drapeaux opérationnels</legend>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_active" class="rounded text-blue-600"/> <span>Actif</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="requires_certification" class="rounded text-amber-600"/> <span>Certification requise (ex: CACES)</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="requires_insurance_proof" class="rounded text-purple-600"/> <span>Assurance pro requise</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="requires_face_check" class="rounded text-rose-600"/> <span>Vérification faciale du prestataire</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_b2b_default" class="rounded text-blue-600"/> <span>Disponible B2B par défaut</span></label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_personal_default" class="rounded text-green-600"/> <span>Disponible particuliers par défaut</span></label>
                        </div>

                        {{--
                            Séparé des autres drapeaux, parce qu'il n'a pas la même portée : il ne
                            décrit pas le service vendu au client mais ce qu'on exige du prestataire
                            avant de lui confier une mission de ce métier.
                        --}}
                        <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                            <label class="inline-flex items-start gap-2">
                                <input type="checkbox" wire:model="taxi_rules" class="mt-1 rounded text-rose-600"/>
                                <span class="text-sm text-gray-700 dark:text-gray-200">
                                    <span class="font-medium">Règles taxi</span> — le prestataire doit déclarer son véhicule
                                    et prouver qu'il a moins de {{ (int) config('fleet_v2.taxi_rules.max_vehicle_age_years', 4) }} ans
                                    (carte grise + assurance).
                                    <span class="mt-1 block text-xs text-gray-500">
                                        Indépendant du fait que le parcours décrive un trajet : une dépanneuse va d'un point
                                        à un autre sans obéir aux règles du transport de personnes.
                                    </span>
                                </span>
                            </label>
                            @error('taxi_rules') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </fieldset>

    <div class="brio-rail-actions">
        <button type="button" class="brio-btn brio-btn-nu"
                x-show="etape > 1" x-cloak
                x-on:click="etape = etape - 1">{{ __('Précédent') }}</button>

        <button type="button" class="brio-btn brio-btn-verre"
                x-show="etape < 4" x-cloak
                x-on:click="etape = etape + 1">{{ __('Suivant') }}</button>
    </div>
</div>


