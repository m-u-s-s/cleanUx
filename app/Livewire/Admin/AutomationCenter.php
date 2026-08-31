<?php

namespace App\Livewire\Admin;

use App\Models\AutomationRule;
use App\Services\Automation\ArmementRefuse;
use App\Services\Automation\Catalogue;
use App\Services\Automation\EtatDeRegle;
use App\Services\FeatureFlag\FeatureFlagService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** La liste des regles : ce qu'elles font, leur etat, et si le moteur agit vraiment. */
class AutomationCenter extends Component
{
    // DEFENSE EN PROFONDEUR (AdminComponentGuardTest) : orthogonale a `manage-automation`,
    // deja verifiee sur la route par `module_gate` — pas une deuxieme source de verite.
    use EnforcesAdminAccess;

    /**
     * LA REGLE CIBLEE PAR LE PANNEAU OUVERT — `#[Locked]`, ET C'EST LA GARDE.
     *
     * Sans elle, le navigateur peut retourner la propriete par `$set` et faire agir le
     * panneau ouvert (motif de suspension compris) sur une autre regle que celle affichee.
     */
    #[Locked]
    public ?int $regleCiblee = null;

    public string $motifSuspension = '';

    /** Le message d'ArmementRefuse, montre — jamais avale ni laisse planter l'ecran. */
    public ?string $erreurArmement = null;

    /** Ouvre le panneau d'actions d'une regle : revalidee, jamais prise telle quelle. */
    public function cibler(int $id): void
    {
        $this->regleCiblee = AutomationRule::query()->findOrFail($id)->id;
        $this->motifSuspension = '';
        $this->erreurArmement = null;
        $this->resetErrorBag();
    }

    public function fermerCible(): void
    {
        $this->reset(['regleCiblee', 'motifSuspension', 'erreurArmement']);
        $this->resetErrorBag();
    }

    public function observer(): void
    {
        app(EtatDeRegle::class)->observer($this->regleVerrouillee());

        $this->apresTransition('Règle mise en observation.');
    }

    /** LE POINT QUI COMPTE — ArmementRefuse se montre, elle ne plante pas et ne s'avale pas. */
    public function armer(): void
    {
        $this->erreurArmement = null;

        try {
            app(EtatDeRegle::class)->armer($this->regleVerrouillee());
        } catch (ArmementRefuse $e) {
            $this->erreurArmement = $e->getMessage();

            return;
        }

        $this->apresTransition('Règle armée.');
    }

    public function suspendre(): void
    {
        $this->validate([
            'motifSuspension' => ['required', 'string', 'min:3'],
        ], attributes: ['motifSuspension' => 'motif']);

        app(EtatDeRegle::class)->suspendre($this->regleVerrouillee(), $this->motifSuspension);

        $this->apresTransition('Règle suspendue.');
    }

    public function desactiver(): void
    {
        app(EtatDeRegle::class)->desactiver($this->regleVerrouillee());

        $this->apresTransition('Règle désactivée.');
    }

    /** La regle ciblee, relue SOUS son identifiant verrouille — jamais un id de plus. */
    protected function regleVerrouillee(): AutomationRule
    {
        abort_if($this->regleCiblee === null, 404);

        return AutomationRule::query()->findOrFail($this->regleCiblee);
    }

    protected function apresTransition(string $message): void
    {
        $this->fermerCible();
        $this->dispatch('toast', $message, 'success');
    }

    public function render(Catalogue $catalogue, FeatureFlagService $drapeaux): View
    {
        $depuis = now()->subDays(7);

        $regles = AutomationRule::query()
            ->withCount(['actionsPosees as actions_sept_jours' => fn ($query) => $query->where('pose_le', '>=', $depuis)])
            ->orderBy('nom')
            ->get();

        return view('livewire.admin.automation-center', [
            'regles' => $regles,
            'declencheurs' => $catalogue->declencheurs(),
            'moteurActif' => $drapeaux->isEnabled('automation'),
        ]);
    }

    /** Cinq etats fixes du modele — pas un catalogue extensible, un match direct est legitime. */
    public function libelleEtat(string $etat): string
    {
        return match ($etat) {
            AutomationRule::ETAT_BROUILLON => 'Brouillon',
            AutomationRule::ETAT_OBSERVATION => 'Observation',
            AutomationRule::ETAT_ARMEE => 'Armée',
            AutomationRule::ETAT_SUSPENDUE => 'Suspendue',
            AutomationRule::ETAT_DESACTIVEE => 'Désactivée',
            default => $etat,
        };
    }

    /** Le jeton de teinte du systeme (`.brio-teinte`), jamais une couleur Tailwind litterale. */
    public function teinteEtat(string $etat): string
    {
        return match ($etat) {
            AutomationRule::ETAT_ARMEE => 'var(--brio-success)',
            AutomationRule::ETAT_OBSERVATION => 'var(--brio-info)',
            AutomationRule::ETAT_SUSPENDUE => 'var(--brio-warning)',
            AutomationRule::ETAT_DESACTIVEE => 'var(--brio-danger)',
            default => 'var(--brio-muted)',
        };
    }
}
