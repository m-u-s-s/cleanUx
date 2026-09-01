<?php

namespace App\Livewire\Admin\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Services\Automation\Catalogue;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Le journal d'une regle : ses passages et ce qu'elle a pose. C'est l'ecran qu'on lit AVANT
 * d'armer — sans lui, l'observation obligatoire ne sert a rien.
 */
class JournalDeRegle extends Component
{
    use EnforcesAdminAccess;

    // `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : la porte de route protege
    // l'AFFICHAGE, pas la lecture d'un journal par appel de methode.
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-automation'), 403);
    }

    /** Les sept resultats fixes de la machine a etats (AutomationAction) — jamais un catalogue. */
    private const RESULTATS = [
        AutomationAction::RESULTAT_SIMULEE,
        AutomationAction::RESULTAT_EXECUTEE,
        AutomationAction::RESULTAT_PROPOSEE,
        AutomationAction::RESULTAT_VALIDEE,
        AutomationAction::RESULTAT_REFUSEE,
        AutomationAction::RESULTAT_ECHOUEE,
        AutomationAction::RESULTAT_EXPIREE,
    ];

    /**
     * LA REGLE DONT ON LIT LE JOURNAL — `#[Locked]`, ET C'EST LA GARDE : sans elle, le
     * navigateur pourrait retourner cette propriete par `$set` et lire le journal d'une AUTRE
     * regle que celle chargee au montage.
     */
    #[Locked]
    public int $regleId;

    public string $filtreResultat = '';

    public function mount(int $regleId): void
    {
        // Revalidee, jamais prise telle quelle — meme discipline que AutomationCenter::cibler().
        $this->regleId = AutomationRule::query()->findOrFail($regleId)->id;
    }

    public function render(Catalogue $catalogue): View
    {
        $regle = AutomationRule::query()->findOrFail($this->regleId);

        $passages = $regle->passages()->orderByDesc('id')->get();

        // `with('decideur')` : la colonne « Décision » nomme l'humain qui a tranche, sinon
        // c'est une requete par ligne posee.
        $lignes = $regle->actionsPosees()
            ->with('decideur')
            ->when($this->filtreResultat !== '', fn ($q) => $q->where('resultat', $this->filtreResultat))
            ->orderByDesc('id')
            ->get();

        return view('livewire.admin.automation.journal-de-regle', [
            'regle' => $regle,
            'passages' => $passages,
            'lignes' => $lignes,
            'resultats' => self::RESULTATS,
            'actionsCatalogue' => $catalogue->actions(),
        ]);
    }

    /** Trois statuts fixes d'un passage (RuleRunner) — match direct legitime, pas un catalogue. */
    public function libelleStatut(string $statut): string
    {
        return match ($statut) {
            'ok' => 'OK',
            'plafond_atteint' => 'Plafond atteint',
            'echec' => 'Échec',
            default => $statut,
        };
    }

    public function teinteStatut(string $statut): string
    {
        return match ($statut) {
            'ok' => 'var(--brio-success)',
            'plafond_atteint' => 'var(--brio-warning)',
            'echec' => 'var(--brio-danger)',
            default => 'var(--brio-muted)',
        };
    }

    /** Deux modes fixes (observation/armee) — la meme legitimite qu'au-dessus. */
    public function libelleMode(string $mode): string
    {
        return match ($mode) {
            'observation' => 'Observation',
            'armee' => 'Armée',
            default => $mode,
        };
    }

    /** Sept resultats fixes de la machine a etats (AutomationAction::RESULTAT_*). */
    public function libelleResultat(string $resultat): string
    {
        return match ($resultat) {
            AutomationAction::RESULTAT_SIMULEE => 'Simulée',
            AutomationAction::RESULTAT_EXECUTEE => 'Exécutée',
            AutomationAction::RESULTAT_PROPOSEE => 'Proposée',
            AutomationAction::RESULTAT_VALIDEE => 'Validée',
            AutomationAction::RESULTAT_REFUSEE => 'Refusée',
            AutomationAction::RESULTAT_ECHOUEE => 'Échouée',
            AutomationAction::RESULTAT_EXPIREE => 'Expirée',
            default => $resultat,
        };
    }

    public function teinteResultat(string $resultat): string
    {
        return match ($resultat) {
            AutomationAction::RESULTAT_EXECUTEE, AutomationAction::RESULTAT_VALIDEE => 'var(--brio-success)',
            AutomationAction::RESULTAT_SIMULEE, AutomationAction::RESULTAT_PROPOSEE => 'var(--brio-info)',
            AutomationAction::RESULTAT_REFUSEE, AutomationAction::RESULTAT_ECHOUEE, AutomationAction::RESULTAT_EXPIREE => 'var(--brio-danger)',
            default => 'var(--brio-muted)',
        };
    }

    /**
     * C'EST CE QUE L'ADMIN VIENT LIRE AVANT D'ARMER — le contenu du parametre, jamais un tableau
     * PHP brut ni son export JSON en pleine cellule.
     */
    public function valeurParametreAffichable(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => '—',
            is_bool($valeur) => $valeur ? 'oui' : 'non',
            is_array($valeur) => json_encode($valeur, JSON_UNESCAPED_UNICODE) ?: '—',
            default => (string) $valeur,
        };
    }
}
