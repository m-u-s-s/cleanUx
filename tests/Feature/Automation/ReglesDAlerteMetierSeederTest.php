<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationRule;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Automation\ValidateurDArbre;
use Database\Seeders\ReferencePlatformSeeder;
use Database\Seeders\ReglesDAlerteMetierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LES CINQ RÈGLES D'ALERTE MÉTIER NAISSENT EN BROUILLON, SANS EXCEPTION. */
class ReglesDAlerteMetierSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Les cinq clés d'alerte enregistrées par `AutomationServiceProvider`. */
    private const CLES = [
        'payment_capture_failed',
        'payout_failed',
        'webhook_backlog',
        'stuck_mission_holding_funds',
        'reconciliation_divergence',
    ];

    public function test_les_cinq_regles_existent_apres_le_seeder(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $this->assertSame(5, AutomationRule::query()->count());

        foreach (self::CLES as $cle) {
            $this->assertDatabaseHas('automation_rules', [
                'declencheur' => "alerte.{$cle}",
                'entite' => 'alerte',
            ]);
        }
    }

    public function test_toutes_naissent_en_brouillon(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $etats = AutomationRule::query()->pluck('etat')->unique()->all();

        $this->assertSame([AutomationRule::ETAT_BROUILLON], $etats);
    }

    /** TÉMOIN — sans lui, un seeder qui n'écrirait rien laisserait passer le test ci-dessus. */
    public function test_temoin_le_seeder_ecrit_bien_cinq_lignes(): void
    {
        $this->assertSame(0, AutomationRule::query()->count());

        $this->seed(ReglesDAlerteMetierSeeder::class);

        $this->assertSame(5, AutomationRule::query()->count());
    }

    public function test_chaque_declencheur_existe_au_registre(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $registre = app(DeclencheurRegistre::class);
        $declencheurs = AutomationRule::query()->pluck('declencheur');

        // ANCRE — sans elle, une boucle sur zéro ligne passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($declencheurs);

        foreach ($declencheurs as $declencheur) {
            $this->assertNotNull($registre->trouver($declencheur), "Déclencheur inconnu du registre : {$declencheur}");
        }
    }

    public function test_chaque_entite_existe_au_registre(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $registre = app(EntiteRegistre::class);
        $entites = AutomationRule::query()->pluck('entite')->unique();

        // ANCRE — sans elle, une boucle sur zéro ligne passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($entites);

        foreach ($entites as $entite) {
            $this->assertNotNull($registre->descripteur($entite), "Entité inconnue du registre : {$entite}");
        }
    }

    public function test_chaque_action_existe_au_registre_et_supporte_l_entite(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $registre = app(ActionRegistre::class);
        $regles = AutomationRule::query()->get();

        // ANCRE — sans elle, une boucle sur zéro ligne passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($regles);

        foreach ($regles as $regle) {
            foreach ($regle->actions as $demande) {
                $action = $registre->trouver($demande['cle']);

                $this->assertNotNull($action, "Action inconnue du registre : {$demande['cle']}");
                $this->assertContains(
                    $regle->entite,
                    $action->entitesSupportees(),
                    "L'action « {$demande['cle']} » ne supporte pas l'entité « {$regle->entite} »."
                );
            }
        }
    }

    /** `ValidateurDArbre` fait autorité : chaque arbre doit s'appliquer réellement contre l'entité. */
    public function test_chaque_arbre_de_conditions_est_valide(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $entiteRegistre = app(EntiteRegistre::class);
        $validateur = app(ValidateurDArbre::class);
        $regles = AutomationRule::query()->get();

        // ANCRE — sans elle, une boucle sur zéro ligne passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($regles);

        foreach ($regles as $regle) {
            $entite = $entiteRegistre->descripteur($regle->entite);
            $erreurs = $validateur->valider($regle->conditions, $entite);

            $this->assertSame([], $erreurs, "Arbre invalide pour {$regle->declencheur} : ".implode(', ', $erreurs));
        }
    }

    /** TÉMOIN — une condition vide serait acceptée par le validateur (voir sa décision documentée) ;
     *  ce test prouve que le seeder pose bien une feuille, pas un arbre vide. */
    public function test_temoin_les_conditions_ne_sont_pas_vides(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $toutesLesConditions = AutomationRule::query()->pluck('conditions');

        // ANCRE — sans elle, une boucle sur zéro ligne passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($toutesLesConditions);

        foreach ($toutesLesConditions as $conditions) {
            $this->assertNotSame([], $conditions);
        }
    }

    /** LE TROU TROUVÉ EN RELECTURE — une règle ADMIN posée AVANT le seed, sur le MÊME
     *  déclencheur qu'une règle système, ne doit jamais empêcher celle-ci d'exister. Matcher
     *  sur `declencheur` le ferait (`payout_failed` déjà pris) ; `cle_de_reference` ne collisionne
     *  jamais, car cette règle admin la laisse NULL. */
    public function test_une_regle_admin_preexistante_sur_le_meme_declencheur_n_empeche_pas_le_seed(): void
    {
        $regleAdmin = AutomationRule::query()->forceCreate([
            'nom' => "Alerte perso d'un administrateur",
            'entite' => 'alerte',
            'declencheur' => 'alerte.payout_failed',
            'conditions' => ['field' => 'cle', 'op' => 'eq', 'value' => 'payout_failed'],
            'actions' => [[
                'cle' => 'notifier.admins',
                'parametres' => ['message' => 'Une alerte personnalisée.'],
            ]],
            // `cle_de_reference` reste NULL : cette règle n'a jamais été semée.
        ]);

        $this->seed(ReglesDAlerteMetierSeeder::class);

        $this->assertSame(6, AutomationRule::query()->count(), 'Cinq règles système + la règle admin préexistante.');
        $this->assertDatabaseHas('automation_rules', [
            'declencheur' => 'alerte.payout_failed',
            'cle_de_reference' => 'systeme.alerte_metier.payout_failed',
        ]);
        $this->assertNotNull(
            AutomationRule::query()->find($regleAdmin->id),
            'La règle admin doit survivre, intacte, à côté de la règle système.'
        );
        $this->assertSame(
            2,
            AutomationRule::query()->where('declencheur', 'alerte.payout_failed')->count(),
            'Les deux règles coexistent bien sur le même déclencheur.'
        );
    }

    public function test_relancer_le_seeder_ne_cree_pas_de_doublon(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $this->assertSame(5, AutomationRule::query()->count());
    }

    /** LE TÉMOIN QUI COMPTE — un administrateur ajuste le nom et le quota ; un redéploiement
     *  qui relance le seeder ne doit ni les écraser, ni faire régresser l'état de la règle. */
    public function test_une_modification_administrateur_survit_a_un_nouveau_passage(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $regle = AutomationRule::query()->where('declencheur', 'alerte.payment_capture_failed')->firstOrFail();

        $regle->forceFill([
            'nom' => 'Paiement raté — nom renommé par un administrateur',
            'quota_par_passage' => 3,
            'etat' => AutomationRule::ETAT_OBSERVATION,
        ])->save();

        $this->seed(ReglesDAlerteMetierSeeder::class);

        $relue = AutomationRule::query()->where('declencheur', 'alerte.payment_capture_failed')->firstOrFail();

        $this->assertSame('Paiement raté — nom renommé par un administrateur', $relue->nom);
        $this->assertSame(3, $relue->quota_par_passage);
        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $relue->etat);
        $this->assertSame(5, AutomationRule::query()->count(), 'Toujours cinq lignes : pas un doublon en plus.');
    }

    /** Le seeder ne touche jamais le drapeau de feature : ce n'est pas son rôle. */
    public function test_le_drapeau_automation_reste_ferme(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $this->assertFalse(config('features.automation'));
    }

    /** LE POINT DE BRANCHEMENT RÉEL — les cinq règles sont des données de référence : elles
     *  doivent apparaître dès que `ReferencePlatformSeeder` tourne (profils `reference` ET
     *  `production`, qui l'appellent tous les deux), pas seulement quand on invoque le seeder seul. */
    public function test_le_seeder_est_branche_dans_le_referentiel_plateforme(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $this->assertSame(5, AutomationRule::query()->count());
    }
}
