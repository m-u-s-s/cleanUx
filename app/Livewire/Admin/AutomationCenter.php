<?php

namespace App\Livewire\Admin;

use App\Models\AutomationRule;
use App\Models\FeatureFlagOverride;
use App\Services\Automation\ArmementRefuse;
use App\Services\Automation\Catalogue;
use App\Services\Automation\EtatDeRegle;
use App\Services\FeatureFlag\FeatureFlagService;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** La liste des regles : ce qu'elles font, leur etat, et si le moteur agit vraiment. */
class AutomationCenter extends Component
{
    // DEFENSE EN PROFONDEUR (AdminComponentGuardTest) : `isAdmin()` seulement.
    use EnforcesAdminAccess;

    // `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : la porte de `module_gate`
    // protege l'AFFICHAGE, pas ce chemin d'action.
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-automation'), 403);
    }

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

    /**
     * ALLUME OU ETEINT LE MOTEUR, DEPUIS LA PAGE QUI ANNONCE SON ETAT.
     *
     * Le bandeau disait « moteur desactive » sans dire ou le rallumer : il fallait connaitre
     * `/admin/feature-flags` et y trouver la clef. Le drapeau reste la source unique — on ecrit
     * la meme derogation que l'ecran des drapeaux, avec la meme trace.
     */
    public function basculerLeMoteur(): void
    {
        abort_unless(Gate::allows('manage-automation'), 403);

        $derogation = FeatureFlagOverride::firstOrNew(['flag_key' => 'automation']);
        $actuel = $derogation->exists
            ? (bool) $derogation->is_enabled
            : (bool) (config('features.automation') === true);

        $derogation->fill([
            'is_enabled' => ! $actuel,
            'reason' => 'Bascule depuis le centre d’automatisation',
            'updated_by_user_id' => Auth::id(),
        ])->save();

        ActivityLogger::log('feature_flag.toggled', $derogation, [
            'flag_key' => 'automation',
            'is_enabled' => $derogation->is_enabled,
        ]);

        $this->dispatch(
            'toast',
            $derogation->is_enabled
                ? 'Moteur d’automatisation activé — les règles armées agissent.'
                : 'Moteur d’automatisation désactivé.',
            'success',
        );
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
            'entites' => $catalogue->entites(),
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
