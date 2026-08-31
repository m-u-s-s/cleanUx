{{--
    LA FILE DES PROPOSITIONS — ce qu'une regle armee a pose sans agir seule, toutes regles
    confondues. Une ligne `proposee` immobilise son entite : c'est un ecran de travail.
--}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Automatisation"
        title="Propositions en attente"
        subtitle="Ce qu'une règle a proposé sans agir seule — à trancher avant que l'entité ne reste figée."
    >
        <x-slot:actions>
            <a href="{{ route('admin.automation') }}" class="brio-btn brio-btn-secondary">← Règles</a>
        </x-slot:actions>
    </x-page-shell>

    <x-table-shell title="File des propositions" subtitle="Une ligne par action proposée, en attente d'une décision humaine.">
        <table class="min-w-full brio-table">
            <thead>
                <tr>
                    <th>Règle</th>
                    <th>Entité</th>
                    <th>Action</th>
                    <th>Paramètres</th>
                    <th>Posé le</th>
                    <th class="text-right">Décision</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lignes as $ligne)
                    <tr wire:key="proposition-{{ $ligne->id }}">
                        <td style="font-size: 0.875rem; color: var(--brio-ink);">{{ $ligne->regle?->nom ?? '—' }}</td>
                        {{-- LE LIBELLE VIENT DU CATALOGUE (Catalogue::entites()), jamais d'une liste en dur. --}}
                        <td class="whitespace-nowrap" style="font-size: 0.875rem; color: var(--brio-muted);">
                            {{ $entites[$ligne->entite_type]['libelle'] ?? $ligne->entite_type }} #{{ $ligne->entite_id }}
                        </td>
                        <td style="font-size: 0.875rem; color: var(--brio-ink);">{{ $actionsCatalogue[$ligne->action_cle]['libelle'] ?? $ligne->action_cle }}</td>
                        {{-- C'EST CE SUR QUOI L'ADMINISTRATEUR DECIDE : lisible, tronque a l'affichage,
                             `title` porte le texte integral — meme patron que JournalDeRegle. --}}
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
                        <td class="whitespace-nowrap" style="font-size: 0.875rem; color: var(--brio-muted);">{{ $ligne->pose_le?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <button type="button" class="brio-btn brio-btn-primary" wire:click="valider({{ $ligne->id }})">Valider</button>
                                <button type="button" class="brio-btn brio-btn-secondary" wire:click="ouvrirRefus({{ $ligne->id }})">Refuser</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state
                                title="Aucune proposition en attente"
                                message="Rien n'attend de décision : toutes les propositions posées ont déjà été tranchées."
                                icon="✅"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-shell>

    {{-- LA CONDITION EST DEHORS DU TELEPORT : `@teleport` rend un `<template x-teleport>` qu'Alpine
         clone A SON INITIALISATION, emis vide s'il n'y a rien a montrer au premier rendu. --}}
    @if($ligneCiblee !== null)
        @teleport('body')
            <div class="brio-modal-fond grid place-items-center p-4" x-data x-on:keydown.escape.window="$wire.fermerRefus()">
                <div class="brio-modal" role="dialog" aria-modal="true" aria-labelledby="titre-refus-proposition">
                    <h2 id="titre-refus-proposition" class="brio-modal-titre">Refuser cette proposition</h2>
                    <p class="brio-modal-texte">Le motif est obligatoire : c'est la seule trace de pourquoi on n'a pas fait.</p>

                    <div class="mt-3">
                        <label class="brio-field-label" for="motifRefus">Motif du refus</label>
                        <textarea id="motifRefus" wire:model="motifRefus" rows="3" placeholder="Pourquoi cette proposition n'est pas retenue ?"></textarea>
                        @error('motifRefus')
                            <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="brio-modal-actions">
                        <button type="button" wire:click="fermerRefus" class="brio-btn brio-btn-secondary">Annuler</button>
                        <button type="button" wire:click="confirmerRefus" class="brio-btn brio-btn-primary">Confirmer le refus</button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
