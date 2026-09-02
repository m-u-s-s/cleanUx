{{-- LA PAGE NE MONTRAIT RIEN : elle filtrait sur l organisation de l administrateur, qui n en a pas.
     Elle gere desormais les sites de TOUTES les societes, et la societe est un champ du formulaire. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Comptes entreprises"
        title="Sites des entreprises"
        subtitle="Les lieux d’intervention déclarés par les sociétés clientes : rattachement, zone de service, contraintes d’accès et contact sur place.">
        <x-slot:actions>
            <span class="brio-inline-stat">{{ $this->reperes['sites'] }} site(s)</span>
            <button type="button" wire:click="ouvrirCreation" class="brio-btn-primary">
                Ajouter un site
            </button>
        </x-slot:actions>
    </x-page-shell>

    {{-- LES QUATRE REPERES SUIVENT LES FILTRES : ils decrivent la liste affichee, pas la base. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi-card title="Sites listés" :value="$this->reperes['sites']" icon="📍" />
        <x-kpi-card title="Sociétés couvertes" :value="$this->reperes['societes']" icon="🏢" />
        <x-kpi-card title="Zones desservies" :value="$this->reperes['zones']" icon="🗺️" />
        <x-kpi-card title="Accès contraint" :value="$this->reperes['contraintes']" icon="🔐" />
    </div>

    <x-filter-panel title="Filtres" subtitle="Isole une société, une zone, un pays ou une contrainte d’accès.">
        <div class="brio-filter-grid-wide">
            <input wire:model.live.debounce.300ms="recherche" type="text"
                   placeholder="Nom, adresse, ville, contact…" aria-label="Recherche" class="xl:col-span-2">

            <select wire:model.live="organisationId" aria-label="Société">
                <option value="">Toutes les sociétés</option>
                @foreach($this->organisations as $organisation)
                    <option value="{{ $organisation->id }}">{{ $organisation->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="zoneId" aria-label="Zone de service">
                <option value="">Toutes les zones</option>
                @foreach($this->zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statut" aria-label="Statut">
                <option value="">Tous statuts</option>
                <option value="active">Actif</option>
                <option value="archived">Archivé</option>
            </select>

            <select wire:model.live="contrainte" aria-label="Contrainte d’accès">
                <option value="">Toutes contraintes</option>
                <option value="badge">Badge requis</option>
                <option value="alarme">Code d’alarme</option>
                <option value="sensible">Zones sensibles</option>
                <option value="parking">Parking disponible</option>
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <select wire:model.live="pays" aria-label="Pays" class="max-w-[12rem]">
                <option value="">Tous les pays</option>
                @foreach($this->paysDisponibles as $code)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>

            <button type="button" wire:click="reinitialiserLesFiltres" class="brio-btn-ligne">
                Réinitialiser
            </button>
        </div>
    </x-filter-panel>

    <x-table-shell>
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Site</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Société</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Adresse</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Zone</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Contact</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Accès</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($sites as $site)
                    <tr wire:key="site-{{ $site->id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold">
                                {{ $site->name }}
                                @if($site->is_primary)
                                    <span class="brio-chip ml-1">Principal</span>
                                @endif
                            </p>
                            <p class="text-xs opacity-70">
                                {{ collect([$site->type, $site->frequencyLabel(), $site->surface_m2 ? $site->surface_m2.' m²' : null])
                                    ->filter()->join(' · ') }}
                            </p>
                        </td>

                        <td class="px-4 py-3">{{ $site->organizationAccount?->name ?? '—' }}</td>

                        <td class="px-4 py-3">
                            <p>{{ $site->address }}</p>
                            <p class="text-xs opacity-70">
                                {{ collect([$site->postal_code, $site->city, $site->country])->filter()->join(' ') }}
                            </p>
                        </td>

                        <td class="px-4 py-3">{{ $site->serviceZone?->name ?? '—' }}</td>

                        <td class="px-4 py-3">
                            <p>{{ $site->contact_name ?: '—' }}</p>
                            <p class="text-xs opacity-70">{{ $site->contact_phone ?: $site->contact_email }}</p>
                        </td>

                        {{-- LES CONTRAINTES SE LISENT D UN COUP D OEIL : c est ce qui decide si un
                             prestataire peut entrer. Le titre porte le mot, la puce porte le signe. --}}
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @if($site->badge_required)
                                    <span class="brio-chip" title="Badge requis">Badge</span>
                                @endif
                                @if($site->alarm_code_required)
                                    <span class="brio-chip" title="Code d’alarme requis">Alarme</span>
                                @endif
                                @if($site->has_sensitive_areas)
                                    <span class="brio-chip" title="Zones sensibles">Sensible</span>
                                @endif
                                @if($site->parking_available)
                                    <span class="brio-chip" title="Parking disponible">Parking</span>
                                @endif
                                @unless($site->badge_required || $site->alarm_code_required || $site->has_sensitive_areas || $site->parking_available)
                                    <span class="text-xs opacity-60">Libre</span>
                                @endunless
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <x-badge :status="$site->status" />
                            <p class="mt-1 text-xs opacity-70">{{ $site->bookings_count }} réservation(s)</p>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="ouvrirEdition({{ $site->id }})"
                                        class="brio-btn-ligne">Modifier</button>

                                @if($site->status === 'archived')
                                    <button type="button" wire:click="reactiver({{ $site->id }})"
                                            class="brio-btn-ligne-accent">Réactiver</button>
                                @else
                                    <button type="button" wire:click="demanderLaSuppression({{ $site->id }})"
                                            class="brio-btn-ligne-danger">Archiver</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-empty-state
                                icon="📍"
                                title="Aucun site"
                                message="Aucun site ne correspond à ces filtres. Réinitialisez-les, ou ajoutez le premier site d’une société." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">{{ $sites->links() }}</div>
    </x-table-shell>

    {{-- ── Formulaire ─────────────────────────────────────────────────────── --}}
    @if($formulaireOuvert)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-formulaire-site">
            <div class="brio-card my-8 w-full max-w-3xl p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="titre-formulaire-site" class="brio-section-title">
                            {{ $siteEnCours ? 'Modifier le site' : 'Nouveau site' }}
                        </h2>
                        <p class="brio-section-subtitle">
                            Le rattachement à une société est obligatoire : un site sans société n’apparaît nulle part.
                        </p>
                    </div>

                    <button type="button" wire:click="fermerLeFormulaire" class="brio-btn-ligne" aria-label="Fermer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                             stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="enregistrer" class="mt-5 space-y-6">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Identité &amp; rattachement</legend>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="site-organisation" class="mb-1 block text-sm font-semibold">Société *</label>
                                <select id="site-organisation" wire:model="organisation" class="w-full">
                                    <option value="">Choisir une société…</option>
                                    @foreach($this->organisations as $organisation)
                                        <option value="{{ $organisation->id }}">{{ $organisation->name }}</option>
                                    @endforeach
                                </select>
                                @error('organisation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="site-nom" class="mb-1 block text-sm font-semibold">Nom du site *</label>
                                <input id="site-nom" wire:model="nom" type="text" class="w-full" placeholder="Siège Bruxelles">
                                @error('nom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="site-type" class="mb-1 block text-sm font-semibold">Type de lieu</label>
                                <input id="site-type" wire:model="type" type="text" class="w-full"
                                       placeholder="bureau, commerce, école, dépôt…" list="types-de-site">
                                <datalist id="types-de-site">
                                    <option value="office">bureau</option>
                                    <option value="retail">commerce</option>
                                    <option value="school">école</option>
                                    <option value="warehouse">dépôt</option>
                                    <option value="restaurant">restaurant</option>
                                </datalist>
                            </div>

                            <div>
                                <label for="site-statut" class="mb-1 block text-sm font-semibold">Statut *</label>
                                <select id="site-statut" wire:model="statutDuSite" class="w-full">
                                    <option value="active">Actif</option>
                                    <option value="archived">Archivé</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="principal" type="checkbox"> Site principal de la société
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="actif" type="checkbox"> Ouvert aux interventions
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Adresse &amp; couverture</legend>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="site-adresse" class="mb-1 block text-sm font-semibold">Adresse *</label>
                                <input id="site-adresse" wire:model="adresse" type="text" class="w-full">
                                @error('adresse') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="site-cp" class="mb-1 block text-sm font-semibold">Code postal *</label>
                                <input id="site-cp" wire:model="codePostal" type="text" class="w-full">
                                @error('codePostal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="site-ville" class="mb-1 block text-sm font-semibold">Ville *</label>
                                <input id="site-ville" wire:model="ville" type="text" class="w-full">
                                @error('ville') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="site-pays" class="mb-1 block text-sm font-semibold">Pays *</label>
                                <input id="site-pays" wire:model="paysDuSite" type="text" maxlength="2"
                                       class="w-full uppercase" placeholder="BE">
                                @error('paysDuSite') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="site-zone" class="mb-1 block text-sm font-semibold">Zone de service</label>
                                <select id="site-zone" wire:model="zoneDeService" class="w-full">
                                    <option value="">Non rattaché</option>
                                    @foreach($this->zones as $zone)
                                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="site-surface" class="mb-1 block text-sm font-semibold">Surface (m²)</label>
                                <input id="site-surface" wire:model="surface" type="number" min="1" class="w-full">
                                @error('surface') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Contact sur place</legend>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label for="site-contact" class="mb-1 block text-sm font-semibold">Nom</label>
                                <input id="site-contact" wire:model="contactNom" type="text" class="w-full">
                            </div>
                            <div>
                                <label for="site-tel" class="mb-1 block text-sm font-semibold">Téléphone</label>
                                <input id="site-tel" wire:model="contactTelephone" type="tel" class="w-full">
                            </div>
                            <div>
                                <label for="site-mail" class="mb-1 block text-sm font-semibold">Courriel</label>
                                <input id="site-mail" wire:model="contactCourriel" type="email" class="w-full">
                                @error('contactCourriel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Accès &amp; contraintes</legend>

                        <div>
                            <label for="site-acces" class="mb-1 block text-sm font-semibold">Instructions d’accès</label>
                            <textarea id="site-acces" wire:model="instructionsAcces" rows="3" class="w-full"
                                      placeholder="Entrée de service à l’arrière, sonner chez le gardien…"></textarea>
                            @error('instructionsAcces') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="badge" type="checkbox"> Badge requis
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="alarme" type="checkbox"> Code d’alarme requis
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="zonesSensibles" type="checkbox"> Zones sensibles
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="parking" type="checkbox"> Parking disponible
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Service attendu</legend>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label for="site-frequence" class="mb-1 block text-sm font-semibold">Fréquence</label>
                                <select id="site-frequence" wire:model="frequence" class="w-full">
                                    <option value="">Non définie</option>
                                    <option value="one_time">Ponctuel</option>
                                    <option value="weekly">Hebdomadaire</option>
                                    <option value="biweekly">Bi-mensuel</option>
                                    <option value="monthly">Mensuel</option>
                                </select>
                            </div>

                            <div>
                                <label for="site-creneau" class="mb-1 block text-sm font-semibold">Créneau préféré</label>
                                <input id="site-creneau" wire:model="creneauPrefere" type="text" class="w-full"
                                       placeholder="matin, après 18h…">
                            </div>

                            <div>
                                <label for="site-prestataire" class="mb-1 block text-sm font-semibold">Prestataire préféré</label>
                                <select id="site-prestataire" wire:model="prestatairePrefere" class="w-full">
                                    <option value="">Aucun</option>
                                    @foreach($this->prestataires as $prestataire)
                                        <option value="{{ $prestataire->id }}">{{ $prestataire->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="site-remarques" class="mb-1 block text-sm font-semibold">Remarques internes</label>
                            <textarea id="site-remarques" wire:model="remarques" rows="2" class="w-full"></textarea>
                        </div>
                    </fieldset>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="fermerLeFormulaire" class="brio-btn-ligne">Annuler</button>
                        <button type="submit" class="brio-btn-primary">
                            {{ $siteEnCours ? 'Enregistrer' : 'Créer le site' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── Confirmation d archivage ───────────────────────────────────────── --}}
    @if($siteASupprimer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-archivage">
            <div class="brio-card w-full max-w-md p-6">
                <h2 id="titre-archivage" class="brio-section-title">Archiver ce site ?</h2>
                <p class="brio-section-subtitle mt-1">
                    Le site n’est pas détruit : ses réservations gardent leur historique. Il disparaît des
                    listes actives et peut être réactivé à tout moment.
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="annulerLaSuppression" class="brio-btn-ligne">Annuler</button>
                    <button type="button" wire:click="archiver" class="brio-btn-danger">Archiver</button>
                </div>
            </div>
        </div>
    @endif
</div>
