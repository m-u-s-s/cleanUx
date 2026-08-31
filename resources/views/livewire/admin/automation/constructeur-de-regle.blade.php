{{--
    LE CONSTRUCTEUR — nom, entité, déclencheur, actions et leurs paramètres, reprise, quotas.
    Les conditions viennent à la tâche suivante : cet écran ne les pose pas. Le vocabulaire vient
    entièrement du catalogue ($entites, $declencheurs, $actionsDisponibles) — rien n'est en dur.
--}}
<div>
    <x-page-shell
        eyebrow="Automatisation"
        :title="$regleId ? 'Modifier la règle' : 'Nouvelle règle'"
        subtitle="Elle naît en brouillon — ce constructeur ne l'arme jamais."
    >
        <x-slot:actions>
            <a href="{{ route('admin.automation') }}" class="brio-btn brio-btn-secondary">← Règles</a>
        </x-slot:actions>
    </x-page-shell>

    <div class="mt-6 space-y-6">
        @if($flash)
            <div role="status" class="brio-alerte brio-alerte-success">{{ $flash }}</div>
        @endif

        <form wire:submit.prevent="enregistrer" class="space-y-6">
            <x-table-shell title="Identité" subtitle="Le nom et la description affichés dans la liste.">
                <div class="brio-form-grid p-5 md:grid-cols-2 md:p-6">
                    <div>
                        <label class="brio-field-label" for="nom">Nom</label>
                        <input id="nom" type="text" wire:model="nom">
                        @error('nom') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="brio-field-label" for="description">Description</label>
                        <textarea id="description" rows="1" wire:model="description"></textarea>
                        @error('description') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-table-shell>

            <x-table-shell title="Entité" subtitle="Ce que la règle balaie. La changer vide le déclencheur et les actions qui ne lui conviennent plus.">
                <div class="brio-form-grid p-5 md:grid-cols-2 md:p-6">
                    <div>
                        <label class="brio-field-label" for="entite">Entité</label>
                        <select id="entite" wire:model.live="entite">
                            <option value="">— Choisir une entité —</option>
                            @foreach($entites as $cle => $descripteur)
                                <option value="{{ $cle }}">{{ $this->libelleEntite($cle) }}</option>
                            @endforeach
                        </select>
                        @error('entite') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-table-shell>

            <x-table-shell title="Déclencheur" subtitle="Une cadence planifiée, ou un événement du métier propre à l'entité choisie.">
                <div class="brio-form-grid p-5 md:grid-cols-2 md:p-6">
                    <div>
                        <label class="brio-field-label" for="declencheur">Déclencheur</label>
                        <select id="declencheur" wire:model.live="declencheur">
                            <option value="">— Choisir un déclencheur —</option>
                            <optgroup label="Planifié">
                                <option value="cadence">Cadence</option>
                            </optgroup>
                            @if($declencheurs !== [])
                                <optgroup label="Événements de l'entité">
                                    @foreach($declencheurs as $cle => $decl)
                                        <option value="{{ $cle }}">{{ $decl['libelle'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        @error('declencheur') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>

                    @if($declencheur === 'cadence')
                        <div>
                            <label class="brio-field-label" for="cadence">Cadence</label>
                            <select id="cadence" wire:model="cadence">
                                @foreach($cadences as $cle => $minutes)
                                    <option value="{{ $cle }}">{{ $this->libelleCadence($cle) }}</option>
                                @endforeach
                            </select>
                            @error('cadence') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            </x-table-shell>

            <x-table-shell
                title="Conditions"
                subtitle="Ce que la règle doit lire vrai pour s'appliquer. Profondeur maximale : {{ $profondeurMax }} niveaux, {{ $noeudsMax }} noeuds au total."
            >
                <div class="space-y-4 p-5 md:p-6">
                    @if($errors->has('conditions'))
                        <div role="alert" class="brio-alerte brio-alerte-danger">
                            @foreach($errors->get('conditions') as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if($entite === '')
                        <x-empty-state
                            title="Choisissez d'abord une entité"
                            message="Le champ et l'opérateur d'une condition dépendent de l'entité choisie."
                            icon="🌳"
                        />
                    @else
                        @include('livewire.admin.automation.partials.noeud-condition', [
                            'noeud' => $conditions,
                            'chemin' => '',
                            'champs' => $champsEntite,
                            'operateurs' => $operateursEntite,
                        ])
                    @endif
                </div>
            </x-table-shell>

            <x-table-shell title="Actions" subtitle="Ce que la règle pose sur chaque entité retenue, avec leurs paramètres.">
                <div class="space-y-4 p-5 md:p-6">
                    @forelse($actions as $i => $action)
                        <div class="brio-choice-card !flex-col !items-stretch !cursor-auto">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-[14rem] flex-1">
                                    <label class="brio-field-label" for="action-{{ $i }}-cle">Action</label>
                                    <select id="action-{{ $i }}-cle" wire:model.live="actions.{{ $i }}.cle">
                                        <option value="">— Choisir une action —</option>
                                        @foreach($actionsDisponibles as $cle => $desc)
                                            <option value="{{ $cle }}">{{ $desc['libelle'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('actions.'.$i.'.cle') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="retirerAction({{ $i }})">Retirer</button>
                            </div>

                            @php($champs = $actionsDisponibles[$action['cle'] ?? '']['champs'] ?? [])
                            @if($champs !== [])
                                <div class="brio-form-grid mt-3 md:grid-cols-2">
                                    @foreach($champs as $param => $type)
                                        <div>
                                            <label class="brio-field-label" for="action-{{ $i }}-{{ $param }}">{{ $param }}</label>
                                            <input id="action-{{ $i }}-{{ $param }}" type="{{ $type === 'nombre' ? 'number' : 'text' }}" wire:model.defer="actions.{{ $i }}.parametres.{{ $param }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-empty-state title="Aucune action" message="Cette règle ne pose encore aucune action." icon="⚙️" />
                    @endforelse

                    <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterAction">+ Ajouter une action</button>
                </div>
            </x-table-shell>

            <x-table-shell title="Reprise et quotas" subtitle="Comment la règle traite les entités déjà vues, et les bornes d'un passage.">
                <div class="brio-form-grid p-5 md:grid-cols-3 md:p-6">
                    <div>
                        <label class="brio-field-label" for="politiqueReprise">Politique de reprise</label>
                        <select id="politiqueReprise" wire:model="politiqueReprise">
                            @foreach($politiques as $cle)
                                <option value="{{ $cle }}">{{ $this->libellePolitique($cle) }}</option>
                            @endforeach
                        </select>
                        @error('politiqueReprise') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="brio-field-label" for="quotaParPassage">Quota par passage</label>
                        <input id="quotaParPassage" type="number" min="1" wire:model="quotaParPassage">
                        @error('quotaParPassage') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="brio-field-label" for="plafondJournalier">Plafond journalier</label>
                        <input id="plafondJournalier" type="number" min="1" wire:model="plafondJournalier">
                        @error('plafondJournalier') <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-table-shell>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="brio-btn brio-btn-primary">{{ $regleId ? 'Mettre à jour' : 'Créer la règle' }}</button>
            </div>
        </form>
    </div>
</div>
