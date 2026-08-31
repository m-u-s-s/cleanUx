<?php

namespace App\Livewire\Admin\Automation;

use App\Models\AutomationRule;
use App\Services\Automation\Catalogue;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Créé et modifie une règle : son nom, son entité, son déclencheur, ses actions, sa reprise et
 * ses quotas. Les conditions viennent à la tâche suivante — cet écran ne les pose pas.
 */
class ConstructeurDeRegle extends Component
{
    use EnforcesAdminAccess;

    /**
     * LA REGLE EN EDITION — `#[Locked]`, ET C'EST LA GARDE : sans elle, le navigateur pourrait
     * retourner cette propriete par `$set` et faire `enregistrer()` mettre a jour une AUTRE regle
     * que celle chargee au montage.
     */
    #[Locked]
    public ?int $regleId = null;

    public string $nom = '';

    public ?string $description = null;

    public string $entite = '';

    public string $declencheur = 'cadence';

    public ?string $cadence = 'quart_heure';

    /**
     * Chaque ligne DEVRAIT porter `cle` et `parametres`, mais la forme n'est garantie qu'APRES
     * `validate()` : Livewire hydrate cette propriete publique depuis la requete, et rien
     * n'empeche un payload construit a la main de soumettre une ligne qui ne les porte pas.
     *
     * @var list<array<string, mixed>>
     */
    public array $actions = [];

    public string $politiqueReprise = 'une_fois';

    public int $quotaParPassage = 50;

    public int $plafondJournalier = 500;

    public ?string $flash = null;

    public function mount(?int $regleId = null): void
    {
        if ($regleId === null) {
            return;
        }

        $regle = AutomationRule::query()->findOrFail($regleId);

        $this->regleId = $regle->id;
        $this->nom = $regle->nom;
        $this->description = $regle->description;
        $this->entite = $regle->entite;
        $this->declencheur = $regle->declencheur;
        $this->cadence = $regle->cadence;
        $this->actions = array_map(fn (array $ligne): array => [
            'cle' => (string) ($ligne['cle'] ?? ''),
            'parametres' => (array) ($ligne['parametres'] ?? []),
        ], $regle->actions ?? []);
        $this->politiqueReprise = $regle->politique_reprise;
        $this->quotaParPassage = $regle->quota_par_passage;
        $this->plafondJournalier = $regle->plafond_journalier;
    }

    /**
     * Hook générique Livewire — appelé pour CHAQUE propriété modifiée, chemins pointés compris
     * (`actions.0.cle`). C'est ici, et seulement ici, que « changer d'entité vide les choix
     * devenus invalides » prend effet : sans ce nettoyage, une règle peut enregistrer un
     * déclencheur ou une action que son entité ne supporte pas — silencieusement inerte, voir
     * `RuleRunner::poser()` qui la refuse ligne par ligne.
     */
    public function updated(string $property): void
    {
        if ($property === 'entite') {
            $this->reinitialiserPourEntite();

            return;
        }

        if ($property === 'declencheur') {
            $this->cadence = $this->declencheur === 'cadence' ? ($this->cadence ?: 'quart_heure') : null;

            return;
        }

        if (preg_match('/^actions\.(\d+)\.cle$/', $property, $m) === 1) {
            $this->actions[(int) $m[1]]['parametres'] = [];
        }
    }

    public function ajouterAction(): void
    {
        $this->actions[] = ['cle' => '', 'parametres' => []];
    }

    public function retirerAction(int $index): void
    {
        unset($this->actions[$index]);
        $this->actions = array_values($this->actions);
    }

    public function enregistrer(): void
    {
        $valide = $this->validate($this->regles(), attributes: $this->libelles());

        $attributs = [
            'nom' => $valide['nom'],
            'description' => $valide['description'] !== '' ? $valide['description'] : null,
            'entite' => $valide['entite'],
            'declencheur' => $valide['declencheur'],
            'cadence' => $valide['declencheur'] === 'cadence' ? $valide['cadence'] : null,
            'actions' => array_map(fn (array $ligne): array => [
                'cle' => $ligne['cle'],
                'parametres' => $ligne['parametres'] ?? [],
            ], $valide['actions'] ?? []),
            'politique_reprise' => $valide['politiqueReprise'],
            'quota_par_passage' => $valide['quotaParPassage'],
            'plafond_journalier' => $valide['plafondJournalier'],
        ];

        if ($this->regleId !== null) {
            // `conditions` N'APPARTIENT PAS A CE CONSTRUCTEUR (tache suivante) : jamais touchee ici.
            AutomationRule::query()->findOrFail($this->regleId)->forceFill($attributs)->save();
            $this->flash = 'Règle mise à jour.';

            return;
        }

        // UNE REGLE NAIT EN BROUILLON, SANS CONDITION : ce constructeur ne l'arme jamais.
        $regle = AutomationRule::query()->create($attributs + [
            'conditions' => [],
            'etat' => AutomationRule::ETAT_BROUILLON,
            'cree_par' => Auth::id(),
        ]);

        $this->reinitialiserLeFormulaire();
        $this->flash = "Règle « {$regle->nom} » créée — elle reste en brouillon.";
    }

    public function render(Catalogue $catalogue): View
    {
        return view('livewire.admin.automation.constructeur-de-regle', [
            'entites' => $catalogue->entites(),
            'declencheurs' => $catalogue->declencheurs($this->entite ?: null),
            'actionsDisponibles' => $catalogue->actions($this->entite ?: null),
            'cadences' => AutomationRule::CADENCES,
            'politiques' => AutomationRule::POLITIQUES_REPRISE,
        ]);
    }

    public function libelleCadence(string $cle): string
    {
        // Quatre valeurs fixes (AutomationRule::CADENCES) — un catalogue extensible n'existe pas ici.
        return match ($cle) {
            'chaque_minute' => 'Chaque minute',
            'quart_heure' => 'Toutes les 15 minutes',
            'heure' => 'Toutes les heures',
            'jour' => 'Chaque jour',
            default => $cle,
        };
    }

    public function libellePolitique(string $cle): string
    {
        // Trois valeurs fixes (AutomationRule::POLITIQUES_REPRISE), meme legitimite qu'au-dessus.
        return match ($cle) {
            'une_fois' => 'Une fois par entité',
            'chaque_passage' => 'À chaque passage',
            'une_fois_par_jour' => 'Une fois par jour et par entité',
            default => $cle,
        };
    }

    /** @return array<string, mixed> */
    protected function regles(): array
    {
        $catalogue = app(Catalogue::class);
        $entites = array_keys($catalogue->entites());
        $declencheursValides = array_merge(['cadence'], array_keys($catalogue->declencheurs($this->entite ?: null)));
        $actionsValides = array_keys($catalogue->actions($this->entite ?: null));

        $reglesCadence = $this->declencheur === 'cadence'
            ? ['required', 'string', Rule::in(array_keys(AutomationRule::CADENCES))]
            : ['nullable', 'string', Rule::in(array_keys(AutomationRule::CADENCES))];

        return [
            'nom' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'entite' => ['required', 'string', Rule::in($entites)],
            'declencheur' => ['required', 'string', Rule::in($declencheursValides)],
            'cadence' => $reglesCadence,
            'politiqueReprise' => ['required', 'string', Rule::in(AutomationRule::POLITIQUES_REPRISE)],
            'quotaParPassage' => ['required', 'integer', 'min:1', 'max:10000'],
            'plafondJournalier' => ['required', 'integer', 'min:1', 'max:100000'],
            'actions' => ['array'],
            'actions.*.cle' => ['required', 'string', Rule::in($actionsValides)],
            'actions.*.parametres' => ['array'],
        ];
    }

    /** @return array<string, string> */
    protected function libelles(): array
    {
        return [
            'nom' => 'nom',
            'entite' => 'entité',
            'declencheur' => 'déclencheur',
            'cadence' => 'cadence',
            'politiqueReprise' => 'politique de reprise',
            'quotaParPassage' => 'quota par passage',
            'plafondJournalier' => 'plafond journalier',
            'actions.*.cle' => 'action',
        ];
    }

    /**
     * L'ENTITE CHANGE : ce qui ne lui convient plus disparait. `cadence` reste valide quelle que
     * soit l'entité — ce n'est pas un déclencheur du registre, c'est le mode « planifié ».
     */
    protected function reinitialiserPourEntite(): void
    {
        $catalogue = app(Catalogue::class);

        if ($this->declencheur !== 'cadence') {
            $declencheursValides = array_keys($catalogue->declencheurs($this->entite ?: null));

            if (! in_array($this->declencheur, $declencheursValides, true)) {
                $this->declencheur = '';
                $this->cadence = null;
            }
        }

        $actionsValides = array_keys($catalogue->actions($this->entite ?: null));

        // Ligne malformee (sans 'cle') : traitee comme invalide pour cette entite, pas comme
        // une erreur — la meme requete non-formulaire pourrait la soumettre a `enregistrer()`,
        // que `validate()` refuserait de toute facon.
        $this->actions = array_values(array_filter(
            $this->actions,
            fn (array $ligne): bool => in_array((string) ($ligne['cle'] ?? ''), $actionsValides, true)
        ));
    }

    protected function reinitialiserLeFormulaire(): void
    {
        $this->regleId = null;
        $this->nom = '';
        $this->description = null;
        $this->entite = '';
        $this->declencheur = 'cadence';
        $this->cadence = 'quart_heure';
        $this->actions = [];
        $this->politiqueReprise = 'une_fois';
        $this->quotaParPassage = 50;
        $this->plafondJournalier = 500;
        $this->resetErrorBag();
    }
}
