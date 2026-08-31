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
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state title="Aucune règle" message="Aucune règle d'automatisation n'a été créée pour le moment." icon="⚙️" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-shell>
</div>
