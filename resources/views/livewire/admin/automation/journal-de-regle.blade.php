{{--
    LE JOURNAL D'UNE REGLE — passages et lignes posees, lus AVANT d'armer. Le libelle d'une
    action vient de $actionsCatalogue (App\Services\Automation\Catalogue), jamais d'une liste en dur.
--}}
<div>
    <x-page-shell
        eyebrow="Automatisation"
        :title="'Journal — '.$regle->nom"
        subtitle="Ce que cette règle a observé ou posé, passage par passage — à lire avant de l'armer."
    >
        <x-slot:actions>
            <a href="{{ route('admin.automation') }}" class="brio-btn brio-btn-secondary">← Règles</a>
        </x-slot:actions>
    </x-page-shell>

    <div class="mt-6 space-y-6">
        <x-table-shell title="Passages" subtitle="Ce que chaque passage a vu, terminé, posé — et pourquoi il s'est arrêté.">
            <table class="min-w-full brio-table">
                <thead>
                    <tr>
                        <th>Mode</th>
                        <th>Démarré le</th>
                        <th>Terminé le</th>
                        <th>Éligibles</th>
                        <th>Vues</th>
                        <th>Terminées</th>
                        <th>Posées</th>
                        <th>Statut</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($passages as $passage)
                        <tr>
                            <td style="font-size: 0.875rem; color: var(--brio-ink);">{{ $this->libelleMode($passage->mode) }}</td>
                            <td class="whitespace-nowrap" style="font-size: 0.875rem; color: var(--brio-muted);">{{ $passage->demarre_le?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap" style="font-size: 0.875rem; color: var(--brio-muted);">{{ $passage->termine_le?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td style="font-size: 0.875rem; color: var(--brio-muted);">{{ $passage->entites_eligibles ?? '—' }}</td>
                            <td style="font-size: 0.875rem; color: var(--brio-muted);">{{ $passage->entites_vues }}</td>
                            <td style="font-size: 0.875rem; color: var(--brio-muted);">{{ count($passage->entites_finies ?? []) }}</td>
                            <td style="font-size: 0.875rem; color: var(--brio-muted);">{{ $passage->actions_posees }}</td>
                            <td>
                                <span class="brio-chip brio-teinte" style="--teinte: {{ $this->teinteStatut($passage->statut) }};">{{ $this->libelleStatut($passage->statut) }}</span>
                            </td>
                            {{-- LE MESSAGE D'UN PASSAGE EN ECHEC EST VISIBLE : c'est lui qui explique une suspension. --}}
                            <td style="font-size: 0.875rem; max-width: 24rem; {{ $this->teinteStatut($passage->statut) === 'var(--brio-danger)' ? 'color: var(--brio-danger); font-weight: 600;' : 'color: var(--brio-muted);' }}">
                                {{ $passage->message ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty-state title="Aucun passage" message="Cette règle n'a encore jamais tourné, ni en observation ni armée." icon="🕒" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-table-shell>

        <x-filter-panel title="Filtrer les lignes posées" subtitle="Isole un résultat parmi ce que la règle a posé.">
            <div class="brio-form-grid md:grid-cols-3">
                <div>
                    <label class="brio-field-label" for="filtreResultat">Résultat</label>
                    <select id="filtreResultat" wire:model.live="filtreResultat">
                        <option value="">Tous</option>
                        @foreach($resultats as $resultat)
                            <option value="{{ $resultat }}">{{ $this->libelleResultat($resultat) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filter-panel>

        <x-table-shell title="Lignes posées" subtitle="Une ligne par action posée sur une entité, avec son résultat.">
            <table class="min-w-full brio-table">
                <thead>
                    <tr>
                        <th>Entité</th>
                        <th>Action</th>
                        <th>Paramètres</th>
                        <th>Mode</th>
                        <th>Résultat</th>
                        <th>Message</th>
                        <th>Posé le</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lignes as $ligne)
                        <tr>
                            <td style="font-size: 0.875rem; color: var(--brio-ink);">{{ $ligne->entite_type }} #{{ $ligne->entite_id }}</td>
                            <td style="font-size: 0.875rem; color: var(--brio-ink);">{{ $actionsCatalogue[$ligne->action_cle]['libelle'] ?? $ligne->action_cle }}</td>
                            {{-- C'EST CE QUE L'ADMIN VIENT LIRE AVANT D'ARMER : lisible, borne a 120
                                 caracteres par valeur, jamais coupe au point de perdre le sens —
                                 `title` porte le texte integral. --}}
                            <td style="font-size: 0.8125rem; color: var(--brio-ink); max-width: 20rem;">
                                @forelse(($ligne->parametres ?? []) as $nom => $valeur)
                                    <div>
                                        <span style="color: var(--brio-muted);">{{ $nom }}</span> :
                                        <span title="{{ $this->valeurParametreAffichable($valeur) }}">{{ \Illuminate\Support\Str::limit($this->valeurParametreAffichable($valeur), 120) }}</span>
                                    </div>
                                @empty
                                    <span style="color: var(--brio-muted);">—</span>
                                @endforelse
                            </td>
                            <td style="font-size: 0.875rem; color: var(--brio-muted);">{{ $this->libelleMode($ligne->mode) }}</td>
                            <td>
                                <span class="brio-chip brio-teinte" style="--teinte: {{ $this->teinteResultat($ligne->resultat) }};">{{ $this->libelleResultat($ligne->resultat) }}</span>
                            </td>
                            {{-- Meme regle de visibilite qu'au tableau des passages : un resultat en echec ressort. --}}
                            <td style="font-size: 0.875rem; max-width: 24rem; {{ $this->teinteResultat($ligne->resultat) === 'var(--brio-danger)' ? 'color: var(--brio-danger); font-weight: 600;' : 'color: var(--brio-muted);' }}">
                                {{ $ligne->message ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap" style="font-size: 0.875rem; color: var(--brio-muted);">{{ $ligne->pose_le?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state title="Aucune ligne" message="Aucune action posée ne correspond à ce filtre." icon="📭" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-table-shell>
    </div>
</div>
