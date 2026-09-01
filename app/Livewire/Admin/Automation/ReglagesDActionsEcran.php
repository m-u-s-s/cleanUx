<?php

namespace App\Livewire\Admin\Automation;

use App\Services\Automation\Catalogue;
use App\Services\Automation\ReglagesDActions;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE SEUL ENDROIT DU PRODUIT OU UN ADMINISTRATEUR DONNE AU MOTEUR LE DROIT D'AGIR SEUL.
 *
 * `toucheAuDomaine()` ne decide PAS de l'autonomie : il decide si la bascule VERS l'autonomie
 * exige une confirmation renforcee avant de prendre effet. Repasser a valider ne l'exige jamais.
 */
class ReglagesDActionsEcran extends Component
{
    use EnforcesAdminAccess;

    // `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : la porte de route protege
    // l'AFFICHAGE, pas `basculer()` ni `confirmerAutonomie()`.
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-automation'), 403);
    }

    /**
     * L'ACTION EN ATTENTE DE CONFIRMATION RENFORCEE — `#[Locked]`, ET C'EST LA GARDE : sans
     * elle, le navigateur pourrait retourner cette propriete par `$set` et faire
     * `confirmerAutonomie()` armer une AUTRE action que celle affichee dans le panneau ouvert.
     */
    #[Locked]
    public ?string $actionEnConfirmation = null;

    /** LE MOT A RETAPER — verifie ICI, jamais par le navigateur : c'est ce qui rend la
     *  confirmation renforcee opposable a `/livewire/update`, qui n'execute aucun JavaScript. */
    public const MOT_DE_CONFIRMATION = 'OUI';

    public string $motDeConfirmation = '';

    /**
     * BASCULE UN REGLAGE. Repasser a valider prend toujours effet ici, tout de suite : c'est
     * le sens SUR de la bascule. Rendre autonome une action qui touche au domaine ne bascule
     * PAS ici — elle ouvre la confirmation renforcee, achevee par `confirmerAutonomie()`
     * seulement.
     */
    public function basculer(string $actionCle, bool $autonome, Catalogue $catalogue, ReglagesDActions $reglages): void
    {
        $descripteur = $catalogue->actions()[$actionCle] ?? null;

        abort_if($descripteur === null, 404);

        // Le mot d'une confirmation ne sert jamais pour une autre action, ni une seconde fois.
        $this->motDeConfirmation = '';
        $this->resetErrorBag();

        if ($autonome && $descripteur['touche_au_domaine']) {
            $this->actionEnConfirmation = $actionCle;

            return;
        }

        $reglages->basculer($actionCle, $autonome, Auth::user());

        // Un reglage direct referme une confirmation restee ouverte sur une AUTRE action.
        $this->actionEnConfirmation = null;
    }

    /** ACHEVE CE QUE `basculer()` A OUVERT — c'est ici, et seulement ici, que l'autonomie prend effet. */
    public function confirmerAutonomie(Catalogue $catalogue, ReglagesDActions $reglages): void
    {
        // AUCUNE CONFIRMATION EN ATTENTE, OU L'ACTION A DISPARU DU REGISTRE ENTRE LES DEUX
        // APPELS (deploiement en cours) : UN SEUL controle explicite couvre les deux, jamais
        // la conversion PHP null -> '' d'une cle de tableau.
        $descripteur = $this->actionEnConfirmation !== null
            ? ($catalogue->actions()[$this->actionEnConfirmation] ?? null)
            : null;

        if ($descripteur === null) {
            $this->actionEnConfirmation = null;
            $this->motDeConfirmation = '';

            return;
        }

        // LE PANNEAU RESTE OUVERT SUR ECHEC : sinon un mot mal tape perd la confirmation en route.
        if (mb_strtoupper(trim($this->motDeConfirmation)) !== self::MOT_DE_CONFIRMATION) {
            $this->addError('motDeConfirmation', 'Tapez « '.self::MOT_DE_CONFIRMATION.' » pour confirmer : le moteur agira seul, sans validation humaine.');

            return;
        }

        $reglages->basculer($this->actionEnConfirmation, true, Auth::user());
        $this->actionEnConfirmation = null;
        $this->motDeConfirmation = '';
    }

    public function annulerConfirmation(): void
    {
        $this->actionEnConfirmation = null;
        $this->motDeConfirmation = '';
        $this->resetErrorBag();
    }

    public function render(Catalogue $catalogue, ReglagesDActions $reglages): View
    {
        return view('livewire.admin.automation.reglages-d-actions-ecran', [
            'actions' => $catalogue->actions(),
            'autonomies' => $reglages->tous(),
        ]);
    }
}
