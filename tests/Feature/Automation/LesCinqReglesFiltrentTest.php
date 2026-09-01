<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationRule;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Automation\ValidateurDArbre;
use App\Services\Conditions\RuleTreeEvaluator;
use Database\Seeders\ReglesDAlerteMetierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UNE RÈGLE DONT LES CONDITIONS NE SÉLECTIONNENT RIEN EST PIRE QU'ABSENTE : elle rassure sans agir. */
class LesCinqReglesFiltrentTest extends TestCase
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

    /** Une alerte persistée par clé : sans elles, rien de réel n'existe à sélectionner. */
    private function semerUneAlertePourChaqueCle(): void
    {
        foreach (self::CLES as $cle) {
            AlerteMetier::query()->create([
                'cle' => $cle,
                'niveau' => 'critique',
                'message' => "Alerte {$cle}",
                'levee_le' => now(),
            ]);
        }
    }

    public function test_chaque_regle_selectionne_sa_propre_alerte_et_pas_une_autre(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);
        $this->semerUneAlertePourChaqueCle();

        $entite = app(EntiteRegistre::class)->descripteur('alerte');
        $evaluateur = app(RuleTreeEvaluator::class);
        $validateur = app(ValidateurDArbre::class);

        $regles = AutomationRule::query()->whereNotNull('cle_de_reference')->get();

        // ANCRE — sans elle, une boucle sur zéro règle passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($regles);
        $this->assertCount(5, $regles);

        foreach ($regles as $regle) {
            $erreurs = $validateur->valider($regle->conditions, $entite);
            $this->assertSame([], $erreurs, "Arbre invalide pour {$regle->declencheur} : ".implode(', ', $erreurs));

            $cleAttendue = str_replace('alerte.', '', (string) $regle->declencheur);

            $requete = $entite->baseQuery();
            $evaluateur->apply($requete, $regle->conditions, $entite);
            $selectionnees = $requete->pluck('cle')->all();

            $this->assertSame(
                [$cleAttendue],
                $selectionnees,
                "La règle {$regle->declencheur} doit sélectionner sa seule clé, pas ".json_encode($selectionnees)
            );
        }
    }

    /** TÉMOIN — sans les cinq alertes semées, la sélection unique ci-dessus serait un vide qui ressemble à un succès. */
    public function test_temoin_les_cinq_alertes_existent_bien_avant_le_filtrage(): void
    {
        $this->semerUneAlertePourChaqueCle();

        $this->assertSame(5, AlerteMetier::query()->count());

        foreach (self::CLES as $cle) {
            $this->assertDatabaseHas('business_alertes', ['cle' => $cle]);
        }
    }
}
