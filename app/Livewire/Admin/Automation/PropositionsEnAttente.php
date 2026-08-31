<?php

namespace App\Livewire\Admin\Automation;

use App\Models\AutomationAction;
use App\Services\Automation\Catalogue;
use App\Services\Automation\DecisionDejaPrise;
use App\Services\Automation\FileDePropositions;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LA FILE DES PROPOSITIONS : ce qu'une regle armee a pose sans agir seule, toutes regles
 * confondues. Une ligne `proposee` immobilise son entite jusqu'a ce qu'un humain tranche ici —
 * c'est un ecran de travail, pas de consultation.
 */
class PropositionsEnAttente extends Component
{
    use EnforcesAdminAccess;

    // `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : la porte de route protege
    // l'AFFICHAGE, pas `valider()` ni `confirmerRefus()`.
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-automation'), 403);
    }

    /**
     * LA LIGNE VISEE PAR LE PANNEAU DE REFUS OUVERT — `#[Locked]`, ET C'EST LA GARDE : sans
     * elle, le navigateur pourrait retourner cette propriete par `$set` et faire
     * `confirmerRefus()` decider une AUTRE proposition que celle affichee dans le panneau.
     */
    #[Locked]
    public ?int $ligneCiblee = null;

    public string $motifRefus = '';

    /** Valider EXECUTE reellement l'action — voir FileDePropositions::valider(). */
    public function valider(int $id, FileDePropositions $file): void
    {
        $ligne = AutomationAction::query()->findOrFail($id);

        try {
            $resultat = $file->valider($ligne, Auth::user());
        } catch (DecisionDejaPrise $e) {
            // DEUX ADMINISTRATEURS SUR LA MEME PROPOSITION, C'EST LE CAS NORMAL D'UN ECRAN
            // TRANSVERSAL : on le montre, on ne plante pas et on ne l'avale pas.
            $this->dispatch('toast', $e->getMessage(), 'warning');

            return;
        }

        $this->dispatch(
            'toast',
            $resultat->reussie ? 'Proposition validée.' : "Échec de l'exécution : {$resultat->message}",
            $resultat->reussie ? 'success' : 'danger',
        );
    }

    /** Ouvre le panneau de refus d'une ligne : revalidee, jamais prise telle quelle. */
    public function ouvrirRefus(int $id): void
    {
        $this->ligneCiblee = AutomationAction::query()->findOrFail($id)->id;
        $this->motifRefus = '';
        $this->resetErrorBag();
    }

    public function fermerRefus(): void
    {
        $this->reset(['ligneCiblee', 'motifRefus']);
        $this->resetErrorBag();
    }

    /** LE MOTIF EST OBLIGATOIRE : c'est la seule trace de pourquoi on n'a pas fait — refuse
     *  cote serveur, pas seulement dans le formulaire. */
    public function confirmerRefus(FileDePropositions $file): void
    {
        $this->validate([
            'motifRefus' => ['required', 'string', 'min:3'],
        ], attributes: ['motifRefus' => 'motif']);

        abort_if($this->ligneCiblee === null, 404);

        $ligne = AutomationAction::query()->findOrFail($this->ligneCiblee);

        try {
            $file->refuser($ligne, Auth::user(), $this->motifRefus);
        } catch (DecisionDejaPrise $e) {
            $this->dispatch('toast', $e->getMessage(), 'warning');
            $this->fermerRefus();

            return;
        }

        $this->dispatch('toast', 'Proposition refusée.', 'success');
        $this->fermerRefus();
    }

    public function render(FileDePropositions $file, Catalogue $catalogue): View
    {
        return view('livewire.admin.automation.propositions-en-attente', [
            'lignes' => $file->enAttente(),
            'entites' => $catalogue->entites(),
            'actionsCatalogue' => $catalogue->actions(),
        ]);
    }

    /**
     * C'EST CE SUR QUOI L'ADMINISTRATEUR DECIDE — meme patron que JournalDeRegle, jamais un
     * tableau PHP brut ni son export JSON en pleine cellule.
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
