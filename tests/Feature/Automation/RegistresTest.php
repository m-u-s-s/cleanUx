<?php

namespace Tests\Feature\Automation;

use App\Livewire\Admin\Automation\ConstructeurDeRegle;
use App\Models\AutomationRule;
use App\Services\Automation\Catalogue;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Automation\ReglagesDActions;
use App\Services\Conditions\RuleTreeEvaluator;
use Database\Seeders\ReglesDAlerteMetierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ce qui doit rester vrai de TOUTE action et de TOUTE entite, y compris celles a venir. */
class RegistresTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_action_declare_une_cle_un_libelle_et_des_entites(): void
    {
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            if ($action->cle() !== $cle) {
                $ecarts[] = "{$cle} : la cle du registre ne correspond pas a cle()";
            }
            if (trim($action->libelle()) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if ($action->entitesSupportees() === []) {
                $ecarts[] = "{$cle} : aucune entite supportee";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_chaque_action_ne_supporte_que_des_entites_enregistrees(): void
    {
        $connues = app(EntiteRegistre::class)->cles();
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            foreach ($action->entitesSupportees() as $entite) {
                if (! in_array($entite, $connues, true)) {
                    $ecarts[] = "{$cle} : entite inconnue « {$entite} »";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_chaque_entite_n_expose_que_des_operateurs_connus(): void
    {
        $ecarts = [];

        foreach (app(EntiteRegistre::class)->cles() as $cle) {
            foreach (app(EntiteRegistre::class)->descripteur($cle)->operators() as $op) {
                if (! in_array($op, RuleTreeEvaluator::OPERATEURS_CONNUS, true)) {
                    $ecarts[] = "{$cle} : operateur inconnu « {$op} »";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** TEMOIN — les deux registres ne sont pas vides. Sans lui, les trois tests ci-dessus
     *  passeraient au vert sur des registres sans rien dedans. */
    public function test_temoin_les_deux_registres_portent_quelque_chose(): void
    {
        $this->assertNotEmpty(app(ActionRegistre::class)->toutes());
        $this->assertNotEmpty(app(EntiteRegistre::class)->cles());
    }

    public function test_chaque_declencheur_declare_cle_libelle_evenement_et_entite_valides(): void
    {
        $ecarts = [];
        $entitesConnues = app(EntiteRegistre::class)->cles();

        foreach (app(DeclencheurRegistre::class)->toutes() as $cle => $declencheur) {
            if ($declencheur->cle() !== $cle) {
                $ecarts[] = "{$cle} : la cle du registre ne correspond pas a cle()";
            }
            if (trim($declencheur->libelle()) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if (! class_exists($declencheur->evenement())) {
                $ecarts[] = "{$cle} : classe d'evenement inexistante « {$declencheur->evenement()} »";
            }
            if (! in_array($declencheur->entite(), $entitesConnues, true)) {
                $ecarts[] = "{$cle} : entite inconnue « {$declencheur->entite()} »";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** TEMOIN — le registre des declencheurs n'est pas vide. Sans lui, le test ci-dessus
     *  passerait au vert sur un registre sans rien dedans. */
    public function test_temoin_le_registre_des_declencheurs_porte_quelque_chose(): void
    {
        $this->assertNotEmpty(app(DeclencheurRegistre::class)->toutes());
    }

    /**
     * LE FORMULAIRE NE DEVINE PAS UN TYPE. `constructeur-de-regle` rend un `input` par type
     * declare ; un type absent de la table y tomberait en `text` sans que rien ne le dise.
     */
    public function test_chaque_action_ne_declare_que_des_types_de_champ_connus_du_formulaire(): void
    {
        $connus = array_keys(ConstructeurDeRegle::TYPES_DE_CHAMP);
        $ecarts = [];
        $champsVus = 0;

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            foreach ($action->champs() as $champ => $type) {
                $champsVus++;

                if (! in_array($type, $connus, true)) {
                    $ecarts[] = "{$cle}.{$champ} : type inconnu du formulaire « {$type} »";
                }
            }
        }

        // TEMOIN — sans champ declare nulle part, la boucle ci-dessus ne mesurerait rien.
        $this->assertGreaterThan(0, $champsVus, 'Aucune action ne declare de champ : ce test ne prouve rien.');
        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /**
     * CHAQUE ENTITE SAIT DIRE SON NOM. Sans ce libelle, une quatrieme entite ajoutee en code
     * arriverait a l'ecran sous sa cle brute, et rien ne le signalerait.
     */
    public function test_chaque_entite_enregistree_porte_un_libelle(): void
    {
        $entites = app(Catalogue::class)->entites();
        $ecarts = [];

        foreach ($entites as $cle => $entite) {
            if (trim((string) ($entite['libelle'] ?? '')) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
        }

        // TEMOIN — le catalogue n'est pas vide : sinon la boucle ci-dessus passerait sur du neant.
        $this->assertGreaterThan(0, count($entites), 'Le catalogue d’entites est vide : ce test ne prouve rien.');
        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /**
     * LE CONTREPOIDS CENTRAL DE LA PHASE 4 : une action qui ecrit dans le domaine ne doit
     * JAMAIS naitre autonome — ni graine, ni migration, seule une decision humaine explicite
     * (l'ecran des reglages) peut la faire basculer. `estAutonome()` fait deja foi : absence de
     * ligne de reglage = a valider, jamais l'inverse (`ReglagesDActions`).
     */
    public function test_aucune_action_touchant_au_domaine_n_est_autonome_par_defaut(): void
    {
        $reglages = app(ReglagesDActions::class);
        $ecarts = [];
        $touchentAuDomaine = 0;

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            if (! $action->toucheAuDomaine()) {
                continue;
            }

            $touchentAuDomaine++;

            if ($reglages->estAutonome($cle)) {
                $ecarts[] = "{$cle} : autonome par defaut, sans decision humaine";
            }
        }

        // TEMOIN — le registre n'est pas vide : sans lui, la boucle ci-dessus ne prouverait rien.
        $this->assertGreaterThan(0, $touchentAuDomaine, 'Aucune action ne touche au domaine : ce test ne prouve rien.');
        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** LA CONVENTION EST LA GARDE. Un nom non qualifie echappe au test d'execution : sur
     *  SQLite un identifiant inconnu devient une chaine litterale au lieu de lever. */
    public function test_chaque_champ_nomme_sa_table(): void
    {
        $ecarts = [];

        foreach (app(EntiteRegistre::class)->cles() as $cle) {
            foreach (app(EntiteRegistre::class)->descripteur($cle)->fields() as $champ => $liaison) {
                if ($liaison->colonne !== null && ! str_contains($liaison->colonne, '.')) {
                    $ecarts[] = "{$cle}.{$champ} : « {$liaison->colonne} » ne nomme pas sa table";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /**
     * LA CLÔTURE : toute règle posée par un seeder naît en brouillon, vise du vocabulaire
     * enregistré, et ne délègue le domaine à personne — jamais un simple `forceCreate`.
     */
    public function test_toute_regle_seedee_est_en_brouillon_et_ne_delegue_pas_le_domaine(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $entitesConnues = app(EntiteRegistre::class)->cles();
        $declencheurs = app(DeclencheurRegistre::class);
        $actions = app(ActionRegistre::class);
        $regles = AutomationRule::query()->whereNotNull('cle_de_reference')->get();

        // TÉMOIN — sans lui, un seeder muet rendrait la boucle ci-dessous vide, donc vraie à tort.
        $this->assertNotEmpty($regles, 'Aucune règle posée par un seeder : ce test ne prouve rien.');

        $ecarts = [];

        foreach ($regles as $regle) {
            if ($regle->etat !== AutomationRule::ETAT_BROUILLON) {
                $ecarts[] = "{$regle->declencheur} : naît en « {$regle->etat} », pas en brouillon";
            }
            if (! in_array($regle->entite, $entitesConnues, true)) {
                $ecarts[] = "{$regle->declencheur} : entité inconnue « {$regle->entite} »";
            }
            if ($regle->declencheur !== 'cadence' && $declencheurs->trouver($regle->declencheur) === null) {
                $ecarts[] = "{$regle->declencheur} : déclencheur inconnu du registre";
            }
            foreach (($regle->actions ?? []) as $demande) {
                $cle = (string) ($demande['cle'] ?? '');
                $action = $actions->trouver($cle);

                if ($action === null) {
                    $ecarts[] = "{$regle->declencheur} : action inconnue « {$cle} »";
                } elseif ($action->toucheAuDomaine()) {
                    $ecarts[] = "{$regle->declencheur} : action « {$cle} » touche au domaine";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }
}
