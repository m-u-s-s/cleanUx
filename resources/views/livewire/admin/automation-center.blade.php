<div class="space-y-6">
    <x-page-shell
        eyebrow="Automatisation"
        title="Centre d'automatisation"
        subtitle="Ce que chaque règle observe, arme et pose — et si le moteur agit vraiment."
    />

    <div role="alert" class="brio-alerte {{ $moteurActif ? 'brio-alerte-success' : 'brio-alerte-warning' }}">
        @if($moteurActif)
            Moteur d'automatisation activé — les règles armées agissent.
        @else
            Moteur d'automatisation désactivé — aucune règle n'agit, quel que soit son état.
        @endif
    </div>

    <x-table-shell title="Règles d'automatisation" subtitle="Nom, entité, déclencheur, état, dernier passage et ce qu'elles ont posé sur sept jours.">
        <table class="min-w-full brio-table">
            <thead>
                <tr>
                    <th>Règle</th>
                    <th>Entité</th>
                    <th>Déclencheur</th>
                    <th>État</th>
                    <th>Dernier passage</th>
                    <th>Posé (7 jours)</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($regles as $regle)
                    <tr>
                        <td>
                            <div class="font-semibold text-slate-900">{{ $regle->nom }}</div>
                            @if($regle->description)
                                <div class="text-xs text-slate-500">{{ $regle->description }}</div>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">{{ $regle->entite }}</td>
                        <td class="text-sm text-slate-600">{{ $declencheurs[$regle->declencheur]['libelle'] ?? $regle->declencheur }}</td>
                        <td>
                            <span class="brio-chip brio-teinte" style="--teinte: {{ $this->teinteEtat($regle->etat) }};">{{ $this->libelleEtat($regle->etat) }}</span>
                        </td>
                        <td class="whitespace-nowrap text-sm text-slate-600">
                            {{ $regle->dernier_passage_le?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="text-sm text-slate-600">{{ $regle->actions_sept_jours }}</td>
                        <td class="text-right">
                            @if($regleCiblee === $regle->id)
                                <button type="button" class="brio-btn brio-btn-ligne brio-btn-nu" wire:click="fermerCible">Fermer</button>
                            @else
                                <button type="button" class="brio-btn brio-btn-ligne brio-btn-nu" wire:click="cibler({{ $regle->id }})">Gérer</button>
                            @endif
                        </td>
                    </tr>
                    @if($regleCiblee === $regle->id)
                        <tr>
                            <td colspan="7">
                                <div class="space-y-3 rounded-lg border p-4" style="border-color: var(--brio-border);">
                                    @if($erreurArmement)
                                        <div role="alert" class="brio-alerte brio-alerte-danger">{{ $erreurArmement }}</div>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="brio-btn brio-btn-secondary" wire:click="observer">Mettre en observation</button>
                                        <button type="button" class="brio-btn brio-btn-primary" wire:click="armer">Armer</button>
                                        <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="desactiver">Désactiver</button>
                                    </div>

                                    <div class="flex flex-wrap items-end gap-2">
                                        <label class="min-w-[16rem] flex-1">
                                            <span class="brio-field-label">Motif de suspension</span>
                                            <input type="text" wire:model="motifSuspension" placeholder="Pourquoi suspendre cette règle ?">
                                            @error('motifSuspension')
                                                <p class="mt-1 text-xs" style="color: var(--brio-danger);">{{ $message }}</p>
                                            @enderror
                                        </label>
                                        <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="suspendre">Suspendre</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state title="Aucune règle" message="Aucune règle d'automatisation n'a été créée pour le moment." icon="⚙️" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-shell>
</div>
