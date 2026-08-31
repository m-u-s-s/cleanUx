<?php

namespace Tests\Feature\Automation;

use App\Services\Automation\Catalogue;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(): Catalogue
    {
        return app(Catalogue::class);
    }

    /** ANCRE — un registre vide rendrait tous les tests ci-dessous verts a vide. */
    public function test_temoin_le_catalogue_porte_quelque_chose(): void
    {
        $this->assertNotEmpty($this->catalogue()->entites());
        $this->assertNotEmpty($this->catalogue()->actions());
        $this->assertNotEmpty($this->catalogue()->declencheurs());
    }

    public function test_chaque_entite_du_registre_est_au_catalogue(): void
    {
        $attendues = app(EntiteRegistre::class)->cles();

        $this->assertSame($attendues, array_keys($this->catalogue()->entites()));
    }

    public function test_les_champs_d_une_entite_viennent_de_son_descripteur(): void
    {
        $descripteur = app(EntiteRegistre::class)->descripteur('booking');

        $this->assertSame(
            array_keys($descripteur->fields()),
            $this->catalogue()->entites()['booking']['champs']
        );
    }

    public function test_les_operateurs_viennent_de_l_evaluateur(): void
    {
        $this->assertSame(
            RuleTreeEvaluator::OPERATEURS_CONNUS,
            $this->catalogue()->entites()['booking']['operateurs']
        );
    }

    /** LE FILTRE PAR ENTITE EST LA GARDE : proposer a l'admin une action que la regle ne
     *  peut pas executer produirait une ligne `echouee` a chaque passage. */
    public function test_les_actions_se_filtrent_par_entite(): void
    {
        $toutes = array_keys($this->catalogue()->actions());
        $pourAlerte = array_keys($this->catalogue()->actions('alerte'));

        $ecarts = [];

        foreach ($pourAlerte as $cle) {
            $action = app(ActionRegistre::class)->trouver($cle);
            if ($action && ! in_array('alerte', $action->entitesSupportees(), true)) {
                $ecarts[] = $cle;
            }
        }

        $this->assertSame([], $ecarts, 'Actions proposees a tort : '.implode(', ', $ecarts));
        $this->assertNotEmpty($pourAlerte, 'Aucune action pour « alerte » : le filtre mesure une panne.');
        $this->assertLessThanOrEqual(count($toutes), count($pourAlerte));
    }

    /** TEMOIN — sans filtre, on obtient bien TOUTES les actions. */
    public function test_temoin_sans_entite_le_catalogue_rend_toutes_les_actions(): void
    {
        $this->assertSame(
            array_keys(app(ActionRegistre::class)->toutes()),
            array_keys($this->catalogue()->actions())
        );
    }

    public function test_les_declencheurs_se_filtrent_par_entite(): void
    {
        foreach ($this->catalogue()->declencheurs('alerte') as $cle => $declencheur) {
            $this->assertSame('alerte', $declencheur['entite'], $cle);
        }

        $this->assertNotEmpty($this->catalogue()->declencheurs('alerte'));
    }

    public function test_chaque_action_expose_son_libelle_et_ses_champs(): void
    {
        $ecarts = [];

        foreach ($this->catalogue()->actions() as $cle => $action) {
            if (trim($action['libelle']) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if (! array_key_exists('champs', $action)) {
                $ecarts[] = "{$cle} : champs absents";
            }
            if (! array_key_exists('touche_au_domaine', $action)) {
                $ecarts[] = "{$cle} : touche_au_domaine absent";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }
}
