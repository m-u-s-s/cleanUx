@php($vehicule = $this->vehicule())

<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Mon annonce')"
        :title="trim($vehicule->titre()) ?: __('Nouveau véhicule')"
        :subtitle="$vehicule->reference">
        <x-slot name="actions">
            <a href="{{ route('peer.owner.vehicles') }}" class="brio-btn-secondary !text-xs">← {{ __('Mes véhicules') }}</a>

            @if ($vehicule->status === 'published')
                <button type="button" wire:click="mettreEnPause" class="brio-btn-secondary !text-xs">{{ __('Mettre en pause') }}</button>
            @elseif (in_array($vehicule->status, ['paused', 'rejected'], true))
                <button type="button" wire:click="reprendre" class="brio-btn-primary !text-xs">{{ __('Remettre en ligne') }}</button>
            @elseif ($vehicule->status === 'draft')
                <button type="button" wire:click="demanderLaPublication" class="brio-btn-primary !text-xs">{{ __('Publier') }}</button>
            @endif
        </x-slot>

        @if ($this->motifsDeBlocage !== [])
            <div class="brio-alerte brio-alerte-warning !mb-0 flex-wrap">
                <span aria-hidden="true">📋</span>
                <span class="font-semibold">{{ __('Avant de publier') }}</span>
                <ul class="w-full list-disc ps-12 text-sm">
                    @foreach ($this->motifsDeBlocage as $motif)
                        <li>{{ $motif }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-page-shell>

    @if ($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif
    @if ($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">

        <div class="space-y-4 lg:col-span-2">

            <x-app-card :title="__('Le véhicule')">
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['brand', __('Marque'), 'text'],
                        ['model', __('Modèle'), 'text'],
                        ['year', __('Année'), 'number'],
                        ['plate', __('Plaque'), 'text'],
                        ['color', __('Couleur'), 'text'],
                        ['seats', __('Places'), 'number'],
                    ] as [$cle, $libelle, $type])
                        <div>
                            <label for="peer-{{ $cle }}" class="brio-field-label">{{ $libelle }}</label>
                            <input id="peer-{{ $cle }}" type="{{ $type }}" wire:model="champs.{{ $cle }}">
                            @error('champs.'.$cle) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="peer-categorie" class="brio-field-label">{{ __('Catégorie') }}</label>
                        <select id="peer-categorie" wire:model="champs.category">
                            @foreach (['citadine', 'berline', 'suv', 'monospace', 'utilitaire', 'cabriolet'] as $categorie)
                                <option value="{{ $categorie }}">{{ ucfirst($categorie) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="peer-transmission" class="brio-field-label">{{ __('Boîte') }}</label>
                        <select id="peer-transmission" wire:model="champs.transmission">
                            <option value="manuelle">{{ __('Manuelle') }}</option>
                            <option value="automatique">{{ __('Automatique') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="peer-fuel" class="brio-field-label">{{ __('Énergie') }}</label>
                        <select id="peer-fuel" wire:model="champs.fuel">
                            @foreach (['essence', 'diesel', 'hybride', 'electrique', 'gpl'] as $energie)
                                <option value="{{ $energie }}">{{ ucfirst($energie) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="peer-description" class="brio-field-label">{{ __('Description') }}</label>
                    <textarea id="peer-description" rows="3" wire:model="champs.description"></textarea>
                </div>
            </x-app-card>

            <x-app-card :title="__('Le prix')" :subtitle="__('Les week-ends et la haute saison sont majorés automatiquement.')">
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['daily_price_cents', __('Prix / jour (€)'), '0.01'],
                        ['deposit_cents', __('Caution (€)'), '1'],
                        ['extra_km_price_cents', __('Km supplémentaire (€)'), '0.01'],
                    ] as [$cle, $libelle, $pas])
                        <div>
                            <label for="peer-{{ $cle }}" class="brio-field-label">{{ $libelle }}</label>
                            <input id="peer-{{ $cle }}" type="number" step="{{ $pas }}" min="0" wire:model="champs.{{ $cle }}">
                            @error('champs.'.$cle) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="peer-km" class="brio-field-label">{{ __('Km inclus / jour') }}</label>
                        <input id="peer-km" type="number" min="0" wire:model="champs.included_km_per_day">
                    </div>

                    @foreach ([
                        ['discount_3_days_percent', __('Remise 3 j (%)')],
                        ['discount_7_days_percent', __('Remise 7 j (%)')],
                        ['discount_28_days_percent', __('Remise 28 j (%)')],
                    ] as [$cle, $libelle])
                        <div>
                            <label for="peer-{{ $cle }}" class="brio-field-label">{{ $libelle }}</label>
                            <input id="peer-{{ $cle }}" type="number" min="0" max="90" wire:model="champs.{{ $cle }}">
                        </div>
                    @endforeach
                </div>
            </x-app-card>

            <x-app-card :title="__('Où et comment')">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label for="peer-adresse" class="brio-field-label">{{ __('Adresse') }}</label>
                        <input id="peer-adresse" type="text" wire:model="champs.address_line">
                    </div>
                    <div>
                        <label for="peer-cp" class="brio-field-label">{{ __('Code postal') }}</label>
                        <input id="peer-cp" type="text" wire:model="champs.postal_code">
                    </div>
                    <div>
                        <label for="peer-ville" class="brio-field-label">{{ __('Ville') }}</label>
                        <input id="peer-ville" type="text" wire:model="champs.city">
                        @error('champs.city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="peer-min-jours" class="brio-field-label">{{ __('Durée min. (j)') }}</label>
                        <input id="peer-min-jours" type="number" min="1" wire:model="champs.min_rental_days">
                    </div>
                    <div>
                        <label for="peer-max-jours" class="brio-field-label">{{ __('Durée max. (j)') }}</label>
                        <input id="peer-max-jours" type="number" min="1" wire:model="champs.max_rental_days">
                    </div>
                    <div>
                        <label for="peer-age" class="brio-field-label">{{ __('Âge min.') }}</label>
                        <input id="peer-age" type="number" min="18" max="99" wire:model="champs.min_driver_age">
                    </div>
                    <div>
                        <label for="peer-permis" class="brio-field-label">{{ __('Permis depuis (ans)') }}</label>
                        <input id="peer-permis" type="number" min="0" max="20" wire:model="champs.min_license_years">
                    </div>
                    <div>
                        <label for="peer-annulation" class="brio-field-label">{{ __('Annulation') }}</label>
                        <select id="peer-annulation" wire:model="champs.cancellation_policy">
                            <option value="souple">{{ __('Souple') }}</option>
                            <option value="moderee">{{ __('Modérée') }}</option>
                            <option value="stricte">{{ __('Stricte') }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="champs.instant_booking" class="rounded border-slate-300 text-sky-600">
                        {{ __('Réservation immédiate — sans validation de ma part') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model.live="champs.delivery_enabled" class="rounded border-slate-300 text-sky-600">
                        {{ __('Je peux livrer le véhicule') }}
                    </label>

                    @if (! empty($champs['delivery_enabled']))
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="peer-rayon-liv" class="brio-field-label">{{ __('Rayon (km)') }}</label>
                                <input id="peer-rayon-liv" type="number" min="0" max="200" wire:model="champs.delivery_radius_km">
                            </div>
                            <div>
                                <label for="peer-prix-liv" class="brio-field-label">{{ __('Prix de la livraison (€)') }}</label>
                                <input id="peer-prix-liv" type="number" step="0.01" min="0" wire:model="champs.delivery_price_cents">
                            </div>
                        </div>
                    @endif
                </div>

                <button type="button" wire:click="enregistrer" class="brio-btn-primary mt-4 !text-xs">
                    {{ __('Enregistrer') }}
                </button>
            </x-app-card>

            <x-app-card :title="__('Photos')">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($vehicule->media as $photo)
                        <div class="brio-list-item !p-2">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}" alt=""
                                 class="h-20 w-full rounded-lg object-cover">
                            <div class="mt-2 flex items-center justify-between gap-1">
                                @if ($photo->is_cover)
                                    <x-ui.badge tone="success" :label="__('Couverture')" />
                                @else
                                    <button type="button" wire:click="definirLaCouverture({{ $photo->id }})"
                                            class="text-[10px] font-semibold text-sky-700 hover:underline">
                                        {{ __('Couverture') }}
                                    </button>
                                @endif
                                <button type="button" wire:click="supprimerUnePhoto({{ $photo->id }})"
                                        class="text-[10px] font-semibold text-red-600 hover:underline">
                                    {{ __('Retirer') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3">
                    <label for="peer-photos" class="brio-field-label">{{ __('Ajouter des photos') }}</label>
                    <input id="peer-photos" type="file" accept="image/*" multiple wire:model="photosAAjouter">
                    <button type="button" wire:click="ajouterDesPhotos" class="brio-btn-secondary mt-2 !text-xs">
                        {{ __('Téléverser') }}
                    </button>
                </div>
            </x-app-card>
        </div>

        <div class="space-y-4">
            <x-app-card :title="__('Papiers')" :subtitle="__('Vérifiés par la plateforme avant publication.')">
                <div class="space-y-2">
                    @forelse ($vehicule->documents as $document)
                        <div class="brio-list-item flex items-center justify-between gap-3 !p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ match ($document->document_type) {
                                        'registration' => __('Carte grise'),
                                        'insurance' => __('Assurance'),
                                        'technical_inspection' => __('Contrôle technique'),
                                        default => $document->document_type,
                                    } }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    @if ($document->expires_at)
                                        {{ __('Expire le') }} {{ $document->expires_at->format('d/m/Y') }}
                                    @else
                                        {{ $document->file_name }}
                                    @endif
                                </p>
                            </div>
                            <x-ui.badge
                                :tone="match ($document->status) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }"
                                :label="match ($document->status) { 'approved' => __('Validé'), 'rejected' => __('Refusé'), default => __('En revue') }"
                                class="flex-shrink-0" />
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Aucun document déposé.') }}</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    <div>
                        <label for="peer-type-doc" class="brio-field-label">{{ __('Type') }}</label>
                        <select id="peer-type-doc" wire:model="typeDocument">
                            <option value="registration">{{ __('Carte grise') }}</option>
                            <option value="insurance">{{ __('Assurance') }}</option>
                            <option value="technical_inspection">{{ __('Contrôle technique') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="peer-exp-doc" class="brio-field-label">{{ __('Expire le') }}</label>
                        <input id="peer-exp-doc" type="date" wire:model="expirationDocument">
                    </div>
                    <div>
                        <label for="peer-fichier-doc" class="brio-field-label">{{ __('Fichier') }}</label>
                        <input id="peer-fichier-doc" type="file" accept=".pdf,image/*" wire:model="fichierDocument">
                        @error('fichierDocument') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="deposerUnDocument" class="brio-btn-secondary w-full !text-xs">
                        {{ __('Déposer') }}
                    </button>
                </div>
            </x-app-card>

            <x-app-card :title="__('Disponibilités')" :subtitle="__('Par défaut le véhicule est disponible ; fermez ce qui ne l’est pas.')">
                <div class="space-y-2">
                    @forelse ($vehicule->availability as $periode)
                        <div class="brio-list-item flex items-center justify-between gap-3 !p-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ $periode->starts_on->format('d/m/Y') }} → {{ $periode->ends_on->format('d/m/Y') }}
                                </p>
                                @if ($periode->reason)
                                    <p class="text-xs text-slate-500">{{ $periode->reason }}</p>
                                @endif
                            </div>
                            <button type="button" wire:click="rouvrirUnePeriode({{ $periode->id }})"
                                    class="flex-shrink-0 text-xs font-semibold text-sky-700 hover:underline">
                                {{ __('Rouvrir') }}
                            </button>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('Aucune période fermée.') }}</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="peer-ferm-du" class="brio-field-label">{{ __('Du') }}</label>
                            <input id="peer-ferm-du" type="date" wire:model="fermetureDebut">
                        </div>
                        <div>
                            <label for="peer-ferm-au" class="brio-field-label">{{ __('Au') }}</label>
                            <input id="peer-ferm-au" type="date" wire:model="fermetureFin">
                        </div>
                    </div>
                    <input type="text" wire:model="fermetureMotif" placeholder="{{ __('Motif (facultatif)') }}"
                           aria-label="{{ __('Motif') }}">
                    <button type="button" wire:click="fermerUnePeriode" class="brio-btn-secondary w-full !text-xs">
                        {{ __('Fermer cette période') }}
                    </button>
                </div>
            </x-app-card>
        </div>
    </div>
</div>
