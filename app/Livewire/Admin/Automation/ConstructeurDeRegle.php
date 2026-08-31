<?php

namespace App\Livewire\Admin\Automation;

use App\Models\AutomationRule;
use App\Services\Automation\Catalogue;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Automation\ValidateurDArbre;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Conditions\RuleTreeTooComplex;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Créé et modifie une règle : son nom, son entité, son déclencheur, ses conditions, ses actions,
 * sa reprise et ses quotas. `ValidateurDArbre` fait autorité sur la forme des conditions.
 */
class ConstructeurDeRegle extends Component
{
    use EnforcesAdminAccess;

    /** Les quatre formes qu'un noeud d'arbre peut prendre — tout le reste est ignore, pas devine. */
    private const TYPES_NOEUD = ['feuille', 'and', 'or', 'not'];

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
     * L'ARBRE DE CONDITIONS — {field, op, value} pour une feuille, {and|or: [...]} ou {not: {...}}
     * pour un noeud composite. `ValidateurDArbre::valider()` fait autorité, jamais reimplemente ici.
     *
     * @var array<string, mixed>
     */
    public array $conditions = [];

    /** LA PORTE JSON — un arbre collé à la main, pas encore validé. Pas `#[Locked]` : l'admin le remplit. */
    public string $conditionsJson = '';

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
        $this->conditions = (array) ($regle->conditions ?? []);
        $this->plafonnerLesBornesDesConditions();
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
        if ($property === 'conditions' || str_starts_with($property, 'conditions.')) {
            // LE RENDU RECURSE SANS BORNE : sans ce garde AVANT le rendu, un `$conditions`
            // profond poste par le client fige le processus bien avant qu'`enregistrer()` ne
            // valide quoi que ce soit (mesure : profondeur 1000 ~19 s, 5000 tue apres 5 min).
            $this->plafonnerLesBornesDesConditions();

            return;
        }

        if ($property === 'entite') {
            $this->reinitialiserPourEntite();
            // LES CHAMPS D'UNE CONDITION APPARTIENNENT A L'ENTITE : en changer rend l'arbre caduc.
            $this->conditions = [];

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

    /**
     * DEFINIT un noeud encore vide (racine, ou enfant unique d'un `not`) : `$chemin` pointe la
     * PLACE du noeud lui-meme dans `$conditions`, en notation pointee ('' pour la racine).
     */
    public function definirNoeud(string $chemin, string $type): void
    {
        if (! in_array($type, self::TYPES_NOEUD, true)) {
            return;
        }

        $this->definirNoeudAu($chemin, $this->noeudVide($type));
        // `$chemin` peut lui-meme encoder une profondeur arbitraire : `data_set` la construit
        // sans jamais la lire, contrairement au rendu qui la recuse ensuite sans borne.
        $this->plafonnerLesBornesDesConditions();
    }

    /** AJOUTE un enfant a la LISTE d'un groupe `and`/`or` : `$cheminListe` pointe cette liste. */
    public function ajouterEnfant(string $cheminListe, string $type): void
    {
        if (! in_array($type, self::TYPES_NOEUD, true)) {
            return;
        }

        $conditions = $this->conditions;
        $liste = (array) data_get($conditions, $cheminListe, []);
        $liste[] = $this->noeudVide($type);
        data_set($conditions, $cheminListe, $liste);
        $this->conditions = $conditions;
        // Meme raison que dans `definirNoeud()` : `$cheminListe` peut deja encoder une profondeur
        // arbitraire.
        $this->plafonnerLesBornesDesConditions();
    }

    /**
     * RETIRE un noeud de sa liste ('' retire la racine, vidant tout l'arbre). Le dernier segment
     * du chemin est TOUJOURS un index de liste : reindexer derriere lui evite un trou.
     */
    public function retirerNoeud(string $chemin): void
    {
        if ($chemin === '') {
            $this->conditions = [];

            return;
        }

        $segments = explode('.', $chemin);
        $index = (int) array_pop($segments);
        $cheminListe = implode('.', $segments);

        $conditions = $this->conditions;
        $liste = (array) data_get($conditions, $cheminListe, []);
        unset($liste[$index]);
        data_set($conditions, $cheminListe, array_values($liste));
        $this->conditions = $conditions;
    }

    /**
     * LA PORTE JSON — colle un arbre écrit à la main. `ValidateurDArbre` fait autorité sur la
     * forme, les champs, les opérateurs ET les bornes ; un arbre bon remplit le MÊME `$conditions`.
     */
    public function appliquerJson(EntiteRegistre $entiteRegistre, ValidateurDArbre $validateurDArbre): void
    {
        $this->resetErrorBag('conditionsJson');

        $arbre = json_decode($this->conditionsJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('conditionsJson', 'JSON invalide : '.json_last_error_msg().'.');

            return;
        }

        if (! is_array($arbre)) {
            $this->addError('conditionsJson', 'Un arbre de conditions doit être {field, op, value} ou {and|or|not: ...}.');

            return;
        }

        $entiteDescripteur = $this->entite !== '' ? $entiteRegistre->descripteur($this->entite) : null;

        if ($entiteDescripteur === null) {
            $this->addError('conditionsJson', $this->entite === ''
                ? "Choisissez d'abord une entité : les conditions se lisent contre ses champs."
                : "Entité inconnue : « {$this->entite} ». Choisissez-en une dans la liste.");

            return;
        }

        $erreurs = $validateurDArbre->valider($arbre, $entiteDescripteur);

        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                $this->addError('conditionsJson', $erreur);
            }

            return;
        }

        // `valider()` a deja borne l'arbre par la meme marche : le replafonner ici serait
        // deux appels du meme controle, pas une garde de plus.
        $this->conditions = $arbre;
    }

    /** @return array<string, mixed> */
    private function noeudVide(string $type): array
    {
        return match ($type) {
            'and' => ['and' => []],
            'or' => ['or' => []],
            'not' => ['not' => []],
            default => ['field' => '', 'op' => '', 'value' => null],
        };
    }

    /** @param  array<string, mixed>  $valeur */
    private function definirNoeudAu(string $chemin, array $valeur): void
    {
        if ($chemin === '') {
            $this->conditions = $valeur;

            return;
        }

        $conditions = $this->conditions;
        data_set($conditions, $chemin, $valeur);
        $this->conditions = $conditions;
    }

    public function enregistrer(EntiteRegistre $entiteRegistre, ValidateurDArbre $validateurDArbre): void
    {
        $valide = $this->validate($this->regles(), attributes: $this->libelles());

        $entiteDescripteur = $entiteRegistre->descripteur($valide['entite']);

        if ($entiteDescripteur === null) {
            // Defense en profondeur : Rule::in(array_keys($catalogue->entites())) le garantit deja.
            $this->addError('conditions', "L'entité choisie n'a pas de descripteur de conditions.");

            return;
        }

        foreach ($validateurDArbre->valider($this->conditions, $entiteDescripteur) as $erreur) {
            $this->addError('conditions', $erreur);
        }

        if ($this->getErrorBag()->has('conditions')) {
            return;
        }

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
            'conditions' => $this->conditions,
            'politique_reprise' => $valide['politiqueReprise'],
            'quota_par_passage' => $valide['quotaParPassage'],
            'plafond_journalier' => $valide['plafondJournalier'],
        ];

        if ($this->regleId !== null) {
            $regle = AutomationRule::query()->findOrFail($this->regleId);
            $etatAvant = $regle->etat;

            // CE QUI CHANGE CE QUE LA REGLE FAIT retrograde une regle deja armee/observee en
            // observation : `armer()` exige un journal, mais ce journal porte sur l'ANCIENNE
            // definition — personne n'a observe la nouvelle. Renommer, changer la description,
            // le quota ou le plafond ne change rien a ce qu'elle FAIT : jamais de retrogradation
            // pour ca seul. `actions`/`conditions` comparent en `!=` (pas `!==`) : MySQL reordonne
            // les cles d'un objet JSON au stockage (piege deja paye sur ce depot), une comparaison
            // stricte y verrait un changement pour une valeur juste reordonnee. Les CONDITIONS
            // filtrent quelles entites la regle touche, au meme titre que l'entite/le declencheur/
            // les actions : les changer retrograde donc aussi.
            $comportementChange = $regle->entite !== $attributs['entite']
                || $regle->declencheur !== $attributs['declencheur']
                || $regle->actions != $attributs['actions']
                || $regle->conditions != $attributs['conditions'];

            $regle->forceFill($attributs)->save();

            if ($comportementChange && $etatAvant !== AutomationRule::ETAT_BROUILLON) {
                app(EtatDeRegle::class)->observer($regle);
            }

            $this->flash = 'Règle mise à jour.';

            return;
        }

        // UNE REGLE NAIT TOUJOURS EN BROUILLON, SES CONDITIONS COMPRISES : jamais armee ici.
        $regle = AutomationRule::query()->create($attributs + [
            'etat' => AutomationRule::ETAT_BROUILLON,
            'cree_par' => Auth::id(),
        ]);

        $this->reinitialiserLeFormulaire();
        $this->flash = "Règle « {$regle->nom} » créée — elle reste en brouillon.";
    }

    public function render(Catalogue $catalogue): View
    {
        $entites = $catalogue->entites();

        return view('livewire.admin.automation.constructeur-de-regle', [
            'entites' => $entites,
            'declencheurs' => $catalogue->declencheurs($this->entite ?: null),
            'actionsDisponibles' => $catalogue->actions($this->entite ?: null),
            'cadences' => AutomationRule::CADENCES,
            'politiques' => AutomationRule::POLITIQUES_REPRISE,
            'champsEntite' => $entites[$this->entite]['champs'] ?? [],
            'operateursEntite' => $entites[$this->entite]['operateurs'] ?? [],
            'profondeurMax' => RuleTreeEvaluator::PROFONDEUR_MAX,
            'noeudsMax' => RuleTreeEvaluator::NOEUDS_MAX,
            'arbreAffichable' => $this->arbreRespecteLesBornes(),
        ]);
    }

    /**
     * Le catalogue ne porte aucun libellé d'entité (`entites()` ne rend que `cle`/`champs`/
     * `operateurs`) — repli lisible (`ucfirst`) si le registre en enregistre une de plus demain.
     */
    public function libelleEntite(string $cle): string
    {
        return match ($cle) {
            'booking' => 'Réservation',
            'alerte' => 'Alerte métier',
            'mission' => 'Mission',
            default => ucfirst($cle),
        };
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
            // AU MOINS UNE ACTION : une regle sans action ne pose jamais rien, brouillon ou pas.
            'actions' => ['array', 'min:1'],
            'actions.*.cle' => ['required', 'string', Rule::in($actionsValides)],
            'actions.*.parametres' => ['array', $this->regleParametresConnus($catalogue)],
        ];
    }

    /**
     * Un parametre absent du `champs()` de l'action choisie est refuse : sans cette garde, un
     * parametre invente passerait au silence jusqu'a ce que `RuleRunner` l'ignore sans le dire.
     */
    protected function regleParametresConnus(Catalogue $catalogue): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echoue) use ($catalogue): void {
            if (preg_match('/^actions\.(\d+)\.parametres$/', $attribut, $m) !== 1) {
                return;
            }

            $cle = (string) ($this->actions[(int) $m[1]]['cle'] ?? '');
            $champsConnus = array_keys($catalogue->actions($this->entite ?: null)[$cle]['champs'] ?? []);
            $inconnus = array_diff(array_keys((array) $valeur), $champsConnus);

            if ($inconnus !== []) {
                $echoue('Paramètre(s) inconnu(s) pour cette action : '.implode(', ', $inconnus).'.');
            }
        };
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
     * BORNE PROFONDEUR ET NOMBRE DE NOEUDS DES QUE L'ARBRE ARRIVE — hydratation (mount),
     * mutation (updated(), definirNoeud(), ajouterEnfant()) ET rendu (render()) : les TROIS
     * partagent LA MEME marche (`RuleTreeEvaluator::verifierLesBornes()`), jamais un parcours
     * maison qui divergerait un jour de celle qui s'applique reellement dans `apply()`.
     */
    protected function plafonnerLesBornesDesConditions(): void
    {
        try {
            app(RuleTreeEvaluator::class)->verifierLesBornes($this->conditions);
        } catch (RuleTreeTooComplex $e) {
            $this->conditions = [];
            $this->addError('conditions', $e->getMessage());
        }
    }

    /**
     * DEFENSE AU RENDU, INDEPENDANTE DE LA MUTATION : si `$conditions` a echappe aux gardes
     * precedentes, le partiel recursif ne doit jamais le parcourir sans le savoir.
     */
    protected function arbreRespecteLesBornes(): bool
    {
        try {
            app(RuleTreeEvaluator::class)->verifierLesBornes($this->conditions);

            return true;
        } catch (RuleTreeTooComplex) {
            return false;
        }
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
        $this->conditions = [];
        $this->actions = [];
        $this->politiqueReprise = 'une_fois';
        $this->quotaParPassage = 50;
        $this->plafondJournalier = 500;
        $this->resetErrorBag();
    }
}
